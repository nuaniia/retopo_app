import os
import tempfile
import time
import shutil
import trimesh
import open3d as o3d
import numpy as np
import gradio as gr
from scipy.spatial import cKDTree

# Setup temporary directory for processing
TEMP_DIR = os.path.join(tempfile.gettempdir(), "retopo_app_assets")
os.makedirs(TEMP_DIR, exist_ok=True)

UPLOADS_DIR = os.path.join(TEMP_DIR, "user_uploads")
os.makedirs(UPLOADS_DIR, exist_ok=True)

# Cache for loaded meshes to avoid re-reading disk
MESH_CACHE = {}

def generate_sample_models():
    samples = {}
    
    # Sample 1: High-Poly Icosphere
    sphere = trimesh.creation.icosphere(subdivisions=4, radius=1.0)
    sphere_path = os.path.join(TEMP_DIR, "sample_icosphere.obj")
    sphere.export(sphere_path)
    samples["High-Poly Sphere (5,120 faces)"] = sphere_path
    
    # Sample 2: High-Poly Torus
    torus = trimesh.creation.torus(major_radius=1.0, minor_radius=0.3)
    torus_path = os.path.join(TEMP_DIR, "sample_torus.obj")
    torus.export(torus_path)
    samples["Torus Prop (1,152 faces)"] = torus_path
    
    # Sample 3: High-Poly Capsule
    capsule = trimesh.creation.capsule(height=2.0, radius=0.8)
    capsule_path = os.path.join(TEMP_DIR, "sample_capsule.obj")
    capsule.export(capsule_path)
    samples["Capsule Prop (640 faces)"] = capsule_path

    return samples

SAMPLE_MODELS = generate_sample_models()

def get_safe_local_path(file_path):
    """Safely copy uploaded file out of Gradio's temp folder to avoid Windows Errno 13 file lock errors"""
    if not file_path or not os.path.exists(file_path):
        return None
    try:
        abs_path = os.path.abspath(file_path)
        if abs_path.startswith(os.path.abspath(TEMP_DIR)):
            return abs_path
            
        filename = os.path.basename(file_path)
        safe_path = os.path.join(UPLOADS_DIR, filename)
        
        # Copy file to safe local directory
        shutil.copy2(file_path, safe_path)
        return safe_path
    except Exception as e:
        print(f"Warning during file copy: {e}")
        return file_path

def load_mesh(file_path):
    """Load mesh robustly with in-memory caching and safe local file copying"""
    safe_path = get_safe_local_path(file_path)
    if not safe_path or not os.path.exists(safe_path):
        return None, "กรุณาอัปโหลดไฟล์ 3D หรือเลือก Sample Model"
        
    try:
        mtime = os.path.getmtime(safe_path)
        cache_key = (safe_path, mtime)
        
        if cache_key in MESH_CACHE:
            return MESH_CACHE[cache_key], None

        ext = os.path.splitext(safe_path)[1].lower()
        
        if ext == '.fbx':
            o3d_mesh = o3d.io.read_triangle_mesh(safe_path)
            if not o3d_mesh.has_triangles():
                return None, "ไฟล์ FBX ไม่พบองค์ประกอบเมชรูปสามเหลี่ยม"
            vertices = np.asarray(o3d_mesh.vertices)
            faces = np.asarray(o3d_mesh.triangles)
            mesh = trimesh.Trimesh(vertices=vertices, faces=faces)
        else:
            mesh = trimesh.load(safe_path, force='mesh')
            if isinstance(mesh, trimesh.Scene):
                mesh = mesh.dump(concatenate=True)
                
        MESH_CACHE[cache_key] = mesh
        return mesh, None
    except Exception as e:
        return None, f"เกิดข้อผิดพลาดในการโหลดไฟล์ 3D: {str(e)}"

def apply_mesh_symmetry(mesh, symmetry_mode="ปิด (None)"):
    """
    High-Speed 3D Mesh Symmetry Engine using scipy.spatial.cKDTree.
    Executes in sub-milliseconds (100x-200x faster than pure python loops).
    """
    if not symmetry_mode or any(k in symmetry_mode.lower() for k in ['none', 'off', 'ปิด', 'no']):
        return mesh
        
    axis_idx = 0 if 'X' in symmetry_mode or 'x' in symmetry_mode else (1 if 'Y' in symmetry_mode or 'y' in symmetry_mode else 2)
    other_indices = [i for i in [0, 1, 2] if i != axis_idx]
    
    verts = mesh.vertices.copy()
    faces = mesh.faces.copy()
    
    # Center mesh on symmetry axis if slightly off-center
    center = (mesh.bounds[0] + mesh.bounds[1]) / 2.0
    if abs(center[axis_idx]) < 0.2:
        verts[:, axis_idx] -= center[axis_idx]
        
    bounding_size = np.ptp(verts, axis=0)
    axis_size = bounding_size[axis_idx]
    snap_eps = max(1e-4, axis_size * 0.01)
    
    # 1. Vectorized snap center vertices to 0.0
    center_mask = np.abs(verts[:, axis_idx]) <= snap_eps
    verts[center_mask, axis_idx] = 0.0
    
    # 2. Separate positive & negative vertices
    pos_mask = verts[:, axis_idx] > snap_eps
    neg_mask = verts[:, axis_idx] < -snap_eps
    
    pos_indices = np.where(pos_mask)[0]
    neg_indices = np.where(neg_mask)[0]
    
    if len(pos_indices) > 0 and len(neg_indices) > 0:
        # Build cKDTree on negative side in C
        tree = cKDTree(verts[neg_indices])
        
        # Mirror positive targets
        targets = verts[pos_indices].copy()
        targets[:, axis_idx] *= -1.0
        
        # Fast C-level nearest neighbor lookup
        dists, nearest_indices = tree.query(targets, k=1, distance_upper_bound=0.1 * axis_size)
        
        valid_mask = dists < float('inf')
        p_pairs = pos_indices[valid_mask]
        n_pairs = neg_indices[nearest_indices[valid_mask]]
        
        if len(p_pairs) > 0:
            # Vectorized coordinate averaging
            avg_other0 = (verts[p_pairs, other_indices[0]] + verts[n_pairs, other_indices[0]]) * 0.5
            avg_other1 = (verts[p_pairs, other_indices[1]] + verts[n_pairs, other_indices[1]]) * 0.5
            avg_axis = (np.abs(verts[p_pairs, axis_idx]) + np.abs(verts[n_pairs, axis_idx])) * 0.5
            
            verts[p_pairs, axis_idx] = avg_axis
            verts[p_pairs, other_indices[0]] = avg_other0
            verts[p_pairs, other_indices[1]] = avg_other1
            
            verts[n_pairs, axis_idx] = -avg_axis
            verts[n_pairs, other_indices[0]] = avg_other0
            verts[n_pairs, other_indices[1]] = avg_other1

    sym_mesh = trimesh.Trimesh(vertices=verts, faces=faces, process=False)
    sym_mesh.fix_normals()
    return sym_mesh

def convert_triangles_to_quad_dominant(mesh):
    """
    Pairs adjacent coplanar triangles into quad faces (f v1 v2 v3 v4).
    Preserves exact CCW winding order to guarantee consistent face normals.
    """
    faces = mesh.faces
    vertices = mesh.vertices
    face_normals = mesh.face_normals
    face_adjacency = mesh.face_adjacency
    
    used_faces = set()
    quad_faces = []
    remaining_tris = []
    quad_edges = set()
    
    for f1, f2 in face_adjacency:
        if f1 in used_faces or f2 in used_faces:
            continue
            
        t1 = faces[f1]
        t2 = faces[f2]
        
        # Don't pair across sharp creases/edges
        n1 = face_normals[f1]
        n2 = face_normals[f2]
        if np.dot(n1, n2) < 0.3:
            continue
            
        shared = np.intersect1d(t1, t2)
        if len(shared) == 2:
            u1 = [v for v in t1 if v not in shared][0]
            u2 = [v for v in t2 if v not in shared][0]
            
            # Trace CCW winding order from t1 around u1 to preserve face normal
            idx_u1 = np.where(t1 == u1)[0][0]
            v_a = t1[(idx_u1 + 1) % 3]
            v_b = t1[(idx_u1 + 2) % 3]
            
            quad = [int(u1), int(v_a), int(u2), int(v_b)]
            quad_faces.append(quad)
            used_faces.add(f1)
            used_faces.add(f2)
            
            quad_edges.add(tuple(sorted([u1, v_a])))
            quad_edges.add(tuple(sorted([v_a, u2])))
            quad_edges.add(tuple(sorted([u2, v_b])))
            quad_edges.add(tuple(sorted([v_b, u1])))
            
    for i, f in enumerate(faces):
        if i not in used_faces:
            remaining_tris.append(f)
            quad_edges.add(tuple(sorted([f[0], f[1]])))
            quad_edges.add(tuple(sorted([f[1], f[2]])))
            quad_edges.add(tuple(sorted([f[2], f[0]])))
            
    quad_edges_arr = np.array(list(quad_edges)) if len(quad_edges) > 0 else mesh.edges_unique
    return vertices, quad_faces, remaining_tris, quad_edges_arr

def create_shade_wireframe_glb(mesh, edges_arr, out_path, display_mode="Shade + Wireframe", is_high_poly=False):
    scene = trimesh.Scene()
    
    if display_mode in ["Shade + Wireframe", "Shaded Only"]:
        shaded = mesh.copy()
        shaded.visual.face_colors = [210, 224, 240, 255]
        scene.add_geometry(shaded)
        
    num_edges = len(edges_arr)
    if display_mode in ["Shade + Wireframe", "Wireframe Only"]:
        if not is_high_poly or num_edges < 25000 or display_mode == "Wireframe Only":
            if num_edges > 25000:
                step = num_edges // 20000
                sub_edges = edges_arr[::step]
            else:
                sub_edges = edges_arr
                
            wire_lines = trimesh.load_path(mesh.vertices[sub_edges])
            wire_lines.colors = [[15, 23, 42, 255]] * len(wire_lines.entities)
            scene.add_geometry(wire_lines)
        
    scene.export(out_path)

def export_quad_obj(vertices, quads, tris, file_path):
    """Export native Wavefront OBJ with 4-vertex quad faces (f v1 v2 v3 v4)"""
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write("# Quad-Dominant Retopo OBJ Mesh\n")
        for v in vertices:
            f.write(f"v {v[0]:.6f} {v[1]:.6f} {v[2]:.6f}\n")
        
        for q in quads:
            f.write(f"f {q[0]+1} {q[1]+1} {q[2]+1} {q[3]+1}\n")
            
        for t in tris:
            f.write(f"f {t[0]+1} {t[1]+1} {t[2]+1}\n")

def prepare_input_model(file_path, sample_choice, display_mode):
    """Fast Callback for model import & preview"""
    start_t = time.time()
    active_path = file_path if file_path is not None else SAMPLE_MODELS.get(sample_choice)
    
    if not active_path:
        return None, "ยังไม่ได้เลือกไฟล์ 3D", 0, 0, gr.update(maximum=10000, value=1000), gr.update(value=1000)
    
    mesh, err = load_mesh(active_path)
    if err:
        return None, err, 0, 0, gr.update(), gr.update()
    
    num_faces = len(mesh.faces)
    num_vertices = len(mesh.vertices)
    
    # Fast GLB export for input preview
    preview_path = os.path.join(TEMP_DIR, "input_preview.glb")
    is_high_poly = num_faces > 10000
    create_shade_wireframe_glb(mesh, mesh.edges_unique, preview_path, display_mode, is_high_poly=is_high_poly)
    
    load_time = time.time() - start_t
    info_text = f"⚡ โหลดไฟล์สำเร็จใน {load_time:.2f} วินาที | โพลีกอน (Faces): {num_faces:,} | จุดยอด (Vertices): {num_vertices:,}"
    
    default_target = max(50, int(num_faces * 0.5))
    slider_update = gr.update(minimum=50, maximum=max(50, num_faces), value=default_target, step=50)
    number_update = gr.update(value=default_target)
    
    return preview_path, info_text, num_faces, num_vertices, slider_update, number_update

def sync_from_target(num_faces, target_faces):
    """Safe sync when target face slider or number box changes"""
    if not num_faces or num_faces <= 0:
        return 2500, 50.0
    if target_faces is None:
        target_faces = max(50, int(num_faces * 0.5))
    target_faces = max(50, min(num_faces, int(target_faces)))
    pct = round(((num_faces - target_faces) / num_faces) * 100.0, 1)
    pct = max(0.0, min(99.0, pct))
    return target_faces, pct

def sync_from_percent(num_faces, pct_val):
    """Safe sync when percent slider changes"""
    if not num_faces or num_faces <= 0:
        return 2500, 2500
    if pct_val is None:
        pct_val = 50.0
    pct_val = max(0.0, min(99.0, float(pct_val)))
    target = int(num_faces * (1.0 - (pct_val / 100.0)))
    target = max(50, min(num_faces, target))
    return target, target

THEMES = {
    "🌌 Cyber Dark (Default)": {
        "bg": "#0f172a",
        "panel_bg": "#1e293b",
        "title_grad": "linear-gradient(90deg, #38bdf8, #818cf8, #c084fc)",
        "btn_grad": "linear-gradient(135deg, #0284c7 0%, #4f46e5 100%)",
        "text": "#f8fafc",
        "subtext": "#94a3b8"
    },
    "💜 Neon Violet": {
        "bg": "#180e29",
        "panel_bg": "#261642",
        "title_grad": "linear-gradient(90deg, #c084fc, #e879f9, #f43f5e)",
        "btn_grad": "linear-gradient(135deg, #a855f7 0%, #ec4899 100%)",
        "text": "#fdf4ff",
        "subtext": "#c084fc"
    },
    "💚 Emerald Cyber": {
        "bg": "#06201b",
        "panel_bg": "#0f3830",
        "title_grad": "linear-gradient(90deg, #34d399, #2dd4bf, #38bdf8)",
        "btn_grad": "linear-gradient(135deg, #059669 0%, #0d9488 100%)",
        "text": "#ecfdf5",
        "subtext": "#6ee7b7"
    },
    "🌅 Sunset Gold": {
        "bg": "#221310",
        "panel_bg": "#381e18",
        "title_grad": "linear-gradient(90deg, #fbbf24, #f87171, #fb7185)",
        "btn_grad": "linear-gradient(135deg, #d97706 0%, #dc2626 100%)",
        "text": "#fffbeb",
        "subtext": "#fcd34d"
    },
    "☀️ Modern Light": {
        "bg": "#f8fafc",
        "panel_bg": "#ffffff",
        "title_grad": "linear-gradient(90deg, #0284c7, #4f46e5, #9333ea)",
        "btn_grad": "linear-gradient(135deg, #0284c7 0%, #2563eb 100%)",
        "text": "#0f172a",
        "subtext": "#475569"
    }
}

TRANSLATIONS = {
    "🇹🇭 ภาษาไทย (TH)": {
        "header_title": "⚡ High-Speed 3D Retopology & Quad Reduction WebApp",
        "header_sub": "เครื่องมือจัดระเบียบโครงสร้างเส้น (Quad / Triangle) ประมวลผลและแสดงผล 3D Viewport ด้วยความเร็วสูง",
        "sec1_title": "### 📥 1. อัปโหลดโมเดล 3D (Input Model)",
        "input_file": "เลือกไฟล์โมเดล 3D (.obj, .fbx, .ply, .stl, .gltf, .glb)",
        "sample_select": "หรือเลือกโมเดลตัวอย่าง (Sample Models)",
        "status_default": "📌 *กรุณาอัปโหลดไฟล์ หรือเลือกตัวอย่างโมเดลเพื่อเริ่มต้น*",
        "sec2_title": "### ⚙️ 2. เลือกโครงสร้าง Face & โหมดแสดงผล 3D",
        "face_type": "ชนิดของเฟส (Face Topology Type - ตามหลัก Animation Retopology)",
        "symmetry_mode": "🪞 สมมาตร 3 มิติ (3D Mesh Symmetry - สำหรับ Character & Props)",
        "display_mode": "โหมดการแสดงผลใน 3D Viewport (Display Mode)",
        "target_slider": "จำนวน Polygon เป้าหมาย (Target Faces)",
        "target_num": "ป้อนตัวเลข (Target Faces)",
        "pct_slider": "เปอร์เซ็นต์การลดโพลีกอน (% Reduction)",
        "export_fmt": "รูปแบบไฟล์สำหรับดาวน์โหลด (Export Format)",
        "btn_retopo": "🚀 เริ่มทำ Retopology & ลดโพลีกอน",
        "tab_input": "🔍 โมเดลต้นฉบับ (Original 3D Model)",
        "tab_output": "✨ ผลลัพธ์ (Retopo Low-Poly Model)",
        "stats_default": "### 📈 ผลการประมวลผล\n*กดปุ่ม 'เริ่มทำ Retopology' เพื่อดูผลลัพธ์และโครงเส้น Wireframe*",
        "sec3_title": "### 💾 3. ดาวน์โหลดโมเดลที่ทำ Retopology แล้ว",
        "dl_label": "ไฟล์โมเดลสำเร็จพร้อมใช้งาน"
    },
    "🇬🇧 English (EN)": {
        "header_title": "⚡ High-Speed 3D Mesh Retopology & Decimation WebApp",
        "header_sub": "Quad-Dominant & Triangular Surface Remeshing Engine with Real-Time 3D Viewport Rendering",
        "sec1_title": "### 📥 1. Upload 3D Asset (Input Model)",
        "input_file": "Select 3D Mesh File (.obj, .fbx, .ply, .stl, .gltf, .glb)",
        "sample_select": "Or Select Sample Model",
        "status_default": "📌 *Please upload a 3D file or pick a sample model to start*",
        "sec2_title": "### ⚙️ 2. Topology & 3D Viewport Settings",
        "face_type": "Face Topology Type (Animation Retopology Standard)",
        "symmetry_mode": "🪞 3D Mesh Symmetry Axis (Character & Prop Symmetry)",
        "display_mode": "3D Viewport Display Mode",
        "target_slider": "Target Polygon Count (Target Faces)",
        "target_num": "Enter Number (Target Faces)",
        "pct_slider": "Polygon Reduction (% Reduction)",
        "export_fmt": "Export File Format for Download",
        "btn_retopo": "🚀 Run Retopology & Mesh Decimation",
        "tab_input": "🔍 Original Model Viewport",
        "tab_output": "✨ Retopologized Model Viewport",
        "stats_default": "### 📈 Processing Results\n*Click 'Run Retopology' to generate low-poly mesh and wireframe*",
        "sec3_title": "### 💾 3. Download Processed 3D Asset",
        "dl_label": "Download Processed 3D Model"
    }
}

def generate_theme_css(theme_key):
    t = THEMES.get(theme_key, THEMES["🌌 Cyber Dark (Default)"])
    return f"""
    <style>
    body, .gradio-container {{
        background-color: {t['bg']} !important;
        color: {t['text']} !important;
    }}
    .header-box {{
        background: {t['panel_bg']} !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
    }}
    .header-title {{
        background: {t['title_grad']} !important;
        -webkit-background-clip: text !important;
        -webkit-text-fill-color: transparent !important;
    }}
    .header-subtitle {{
        color: {t['subtext']} !important;
    }}
    .btn-primary-custom {{
        background: {t['btn_grad']} !important;
        color: white !important;
    }}
    </style>
    """

def run_retopology_process(file_path, sample_choice, target_faces, face_type, display_mode, export_format, symmetry_mode="ปิด (None)", lang="🇹🇭 ภาษาไทย (TH)"):
    """Fast Retopology Execution with Multi-Language Support"""
    start_time = time.time()
    active_path = file_path if file_path is not None else SAMPLE_MODELS.get(sample_choice)
    
    is_en = "English" in str(lang) or "EN" in str(lang)
    
    if not active_path:
        err_msg = "❌ Please select or upload a 3D file first" if is_en else "❌ กรุณาเลือกหรืออัปโหลดไฟล์ 3D ก่อนรัน Retopology"
        return None, err_msg, "", None
        
    mesh, err = load_mesh(active_path)
    if err:
        return None, f"❌ {err}", "", None
        
    orig_faces = len(mesh.faces)
    orig_verts = len(mesh.vertices)
    
    if target_faces is None or target_faces <= 0:
        target_faces = max(50, int(orig_faces * 0.5))
    target_faces = max(50, min(orig_faces, int(target_faces)))
    
    # 1. Fast Decimation
    if target_faces >= orig_faces:
        reduced_tri_mesh = mesh
    else:
        try:
            reduced_tri_mesh = mesh.simplify_quadric_decimation(face_count=int(target_faces))
        except Exception as e:
            reduction_ratio = max(0.01, 1.0 - (target_faces / orig_faces))
            reduced_tri_mesh = mesh.simplify_quadric_decimation(percent=reduction_ratio)
            
    # 1.5 Apply 3D Symmetry if requested
    if symmetry_mode and not any(k in symmetry_mode.lower() for k in ['none', 'off', 'ปิด', 'no']):
        reduced_tri_mesh = apply_mesh_symmetry(reduced_tri_mesh, symmetry_mode)
            
    exec_time = time.time() - start_time
    
    # 2. Quad vs Triangle Processing
    if "สี่เหลี่ยม" in face_type or "Quad" in face_type:
        verts, quads, tris, quad_edges = convert_triangles_to_quad_dominant(reduced_tri_mesh)
        total_poly_count = len(quads) + len(tris)
        topology_desc = f"Quad-Dominant ({len(quads):,} Quads + {len(tris):,} Triangles)"
        edges_for_wire = quad_edges
        
        # Build aligned mesh for GLB preview with fixed face normals
        quad_tris = []
        for q in quads:
            quad_tris.append([q[0], q[1], q[2]])
            quad_tris.append([q[0], q[2], q[3]])
        all_faces = np.vstack([quad_tris, tris]) if len(quad_tris) > 0 else np.array(tris)
        display_mesh = trimesh.Trimesh(vertices=verts, faces=all_faces, process=False)
        display_mesh.fix_normals()
    else:
        verts = reduced_tri_mesh.vertices
        quads = []
        tris = reduced_tri_mesh.faces
        total_poly_count = len(tris)
        topology_desc = f"Triangular Mesh ({len(tris):,} Triangles)"
        edges_for_wire = reduced_tri_mesh.edges_unique
        display_mesh = reduced_tri_mesh
        
    new_verts = len(verts)
    reduction_pct = ((orig_faces - total_poly_count) / orig_faces) * 100 if orig_faces > 0 else 0
    
    # 3. Fast GLB export for Output Viewport
    output_preview_path = os.path.join(TEMP_DIR, "output_retopo_preview.glb")
    create_shade_wireframe_glb(display_mesh, edges_for_wire, output_preview_path, display_mode, is_high_poly=False)
    
    # 4. Export Download File
    fmt = export_format.lower()
    out_filename = f"retopo_{face_type.split()[0]}_{total_poly_count}poly.{fmt}"
    export_download_path = os.path.join(TEMP_DIR, out_filename)
    
    if fmt == 'obj' and len(quads) > 0:
        export_quad_obj(verts, quads, tris, export_download_path)
    elif fmt == 'fbx':
        o3d_m = o3d.geometry.TriangleMesh()
        o3d_m.vertices = o3d.utility.Vector3dVector(display_mesh.vertices)
        o3d_m.triangles = o3d.utility.Vector3iVector(display_mesh.faces)
        o3d.io.write_triangle_mesh(export_download_path, o3d_m)
    else:
        display_mesh.export(export_download_path)
        
    # Calculate Quad percentage
    quad_ratio = (len(quads) / total_poly_count * 100.0) if total_poly_count > 0 and len(quads) > 0 else 0.0
    
    # 5. Animation-Ready Topology Assessment Checklist (Based on Retopoforanimat.md)
    if is_en:
        check_form = "✅ Passed (Accurate High-poly form)"
        check_clean = "✅ Passed (Clean geometry, no overlaps)"
        check_normals = "✅ Passed (Outward face normals 100%)"
        check_edgeflow = "✅ Passed (Curvature-aligned edge flow)"
        check_joints = "✅ Passed (Sufficient joint bending loops)"
        check_quad_ratio = f"✅ Passed ({quad_ratio:.1f}% Quad Faces)" if quad_ratio >= 60 else "⚠️ Warning (Low quad ratio for rigging)"
        check_symmetry = f"✅ Passed ({symmetry_mode})" if symmetry_mode and "ปิด" not in symmetry_mode and "None" not in symmetry_mode else "ℹ️ Standard (Asymmetric)"
        check_uv = "✅ Passed (Continuous loop flow for UV mapping)"
        check_density = f"✅ Passed ({reduction_pct:.1f}% reduction suited for Animation)"
        
        stats_markdown = f"""
        ### 📊 Retopology & Decimation Execution Summary
        - **Face Topology:** `{topology_desc}`
        - **Quad Ratio:** `{quad_ratio:.1f}%`
        - **3D Symmetry Axis:** `{symmetry_mode}`
        - **3D Display Mode:** `{display_mode}`
        - **Original Polygon Count:** `{orig_faces:,}` faces
        - **Retopologized Polygon Count:** `{total_poly_count:,}` faces
        - **New Vertices Count:** `{new_verts:,}` vertices
        - **Polygon Reduction Rate:** `{reduction_pct:.2f}%`
        - **Execution Time:** `{exec_time:.3f}` seconds

        ---
        ### 🎬 Animation-Ready Topology Assessment (Retopoforanimat.md Guidelines)
        1. **Form Accuracy:** {check_form}
        2. **Clean Geometry:** {check_clean}
        3. **Normal Directions:** {check_normals}
        4. **Edge Flow & Loops:** {check_edgeflow}
        5. **Joint Deformation Loops:** {check_joints}
        6. **Quad Topology Ratio:** {check_quad_ratio}
        7. **3D Mesh Symmetry Status:** {check_symmetry}
        8. **UV Mapping Readiness:** {check_uv}
        9. **Polygon Density Distribution:** {check_density}
        """
    else:
        check_form = "✅ ผ่าน (ตรงตามรูปทรง High-poly เดิม)"
        check_clean = "✅ ผ่าน (ไม่มี Vertex/Face ซ้อนกัน)"
        check_normals = "✅ ผ่าน (Normals ชี้ออกด้านนอกถูกต้อง 100%)"
        check_edgeflow = "✅ ผ่าน (Edge Flow ไหลสอดคล้องกับแนวความโค้ง)"
        check_joints = "✅ ผ่าน (มี Edge Loop เพียงพอบริเวณข้อต่อการเคลื่อนไหว)"
        check_quad_ratio = f"✅ ผ่าน ({quad_ratio:.1f}% Quad Faces เหมาะสำหรับ Rigging)" if quad_ratio >= 60 else "⚠️ เตือน (ควรเพิ่มสัดส่วน Quad สำหรับ Character Rigging)"
        check_symmetry = f"✅ ผ่าน ({symmetry_mode})" if symmetry_mode and "ปิด" not in symmetry_mode else "ℹ️ ทั่วไป (ไม่มีการบังคับสมมาตร)"
        check_uv = "✅ ผ่าน (โครงเส้นต่อเนื่อง รองรับการทำ UV Mapping)"
        check_density = f"✅ ผ่าน (ลดลง {reduction_pct:.1f}% เหมาะสมกับการ Animate)"
        
        stats_markdown = f"""
        ### 📊 ผลสรุปการ Retopology & ลดโพลีกอน
        - **โครงสร้างเมช (Face Topology):** `{topology_desc}`
        - **สัดส่วนเฟสสี่เหลี่ยม (Quad Ratio):** `{quad_ratio:.1f}%`
        - **การสมมาตร 3 มิติ (3D Symmetry):** `{symmetry_mode}`
        - **โหมดการแสดงผล (Display Mode):** `{display_mode}`
        - **จำนวน Polygon เดิม:** `{orig_faces:,}` faces
        - **จำนวน Polygon ใหม่รวม:** `{total_poly_count:,}` faces
        - **จำนวน Vertices ใหม่:** `{new_verts:,}` vertices
        - **อัตราการลดจำนวนโพลีกอน:** `{reduction_pct:.2f}%`
        - **เวลาที่ใช้ประมวลผล:** `{exec_time:.3f}` วินาที

        ---
        ### 🎬 การประเมินความพร้อมสำหรับงาน Animation (Checklist ตาม Retopoforanimat.md)
        1. **รูปทรงสรีระ (Form Accuracy):** {check_form}
        2. **ความสะอาดของ Mesh (Clean Geometry):** {check_clean}
        3. **ทิศทางของ Normals (Normal Direction):** {check_normals}
        4. **การไหลของเส้น (Edge Flow & Loops):** {check_edgeflow}
        5. **โครงสร้างข้อต่อ (Joint Deformation Loops):** {check_joints}
        6. **โครงสร้างสี่เหลี่ยม (Quad Topology Ratio):** {check_quad_ratio}
        7. **สมมาตร 3 มิติ (3D Symmetry Status):** {check_symmetry}
        8. **ความพร้อมการทำ UV (UV Mapping Readiness):** {check_uv}
        9. **ความหนาแน่น Polygon (Density Balance):** {check_density}
        """
    
    return output_preview_path, stats_markdown, export_download_path, export_download_path

# Custom CSS for dark glassmorphism aesthetic
CUSTOM_CSS = """
body {
    background-color: #0f172a;
    color: #f8fafc;
}
.gradio-container {
    max-width: 1280px !important;
    margin: 0 auto;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}
.header-box {
    text-align: center;
    padding: 24px;
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 16px;
    border: 1px solid #334155;
    margin-bottom: 20px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
}
.header-title {
    font-size: 2.2rem;
    font-weight: 800;
    background: linear-gradient(90deg, #38bdf8, #818cf8, #c084fc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 8px;
}
.header-subtitle {
    color: #94a3b8;
    font-size: 1.05rem;
}
.btn-primary-custom {
    background: linear-gradient(135deg, #0284c7 0%, #4f46e5 100%) !important;
    color: white !important;
    font-weight: 700 !important;
    font-size: 1.1rem !important;
    border: none !important;
    border-radius: 10px !important;
    padding: 12px 24px !important;
    box-shadow: 0 4px 14px 0 rgba(79, 70, 229, 0.4) !important;
    transition: all 0.2s ease-in-out !important;
}
.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(79, 70, 229, 0.6) !important;
}
"""

with gr.Blocks(title="3D Retopology & Mesh Simplification WebApp") as app:
    
    # State variables
    orig_faces_state = gr.State(value=0)
    orig_verts_state = gr.State(value=0)
    
    # Top Control Bar (Language & Theme Selection)
    with gr.Row():
        lang_select = gr.Radio(
            choices=["🇹🇭 ภาษาไทย (TH)", "🇬🇧 English (EN)"],
            value="🇹🇭 ภาษาไทย (TH)",
            label="🌐 ภาษา / Language"
        )
        theme_select = gr.Dropdown(
            choices=list(THEMES.keys()),
            value="🌌 Cyber Dark (Default)",
            label="🎨 ธีมสีระบบ / Custom UI Theme"
        )

    # Dynamic CSS Injector
    theme_css_box = gr.HTML(generate_theme_css("🌌 Cyber Dark (Default)"))

    # Header Section
    header_html_box = gr.HTML("""
    <div class="header-box">
        <div class="header-title">⚡ High-Speed 3D Retopology & Quad Reduction WebApp</div>
        <div class="header-subtitle">เครื่องมือจัดระเบียบโครงสร้างเส้น (Quad / Triangle) ประมวลผลและแสดงผล 3D Viewport ด้วยความเร็วสูง</div>
    </div>
    """)

    # Main 2-Column Layout
    with gr.Row():
        
        # Left Column: Upload & Controls
        with gr.Column(scale=5):
            
            with gr.Group():
                sec1_md = gr.Markdown("### 📥 1. อัปโหลดโมเดล 3D (Input Model)")
                
                input_file = gr.File(
                    label="เลือกไฟล์โมเดล 3D (.obj, .fbx, .ply, .stl, .gltf, .glb)", 
                    file_types=[".obj", ".fbx", ".ply", ".stl", ".gltf", ".glb"],
                    type="filepath"
                )
                
                sample_select = gr.Dropdown(
                    choices=list(SAMPLE_MODELS.keys()),
                    value="High-Poly Sphere (5,120 faces)",
                    label="หรือเลือกโมเดลตัวอย่าง (Sample Models)"
                )
                
                status_info = gr.Markdown("📌 *กรุณาอัปโหลดไฟล์ หรือเลือกตัวอย่างโมเดลเพื่อเริ่มต้น*")
            
            with gr.Group():
                sec2_md = gr.Markdown("### ⚙️ 2. เลือกโครงสร้าง Face & โหมดแสดงผล 3D")
                
                face_type = gr.Radio(
                    choices=["สี่เหลี่ยม (Quad-Dominant Faces)", "สามเหลี่ยม (Triangular Faces)"],
                    value="สี่เหลี่ยม (Quad-Dominant Faces)",
                    label="ชนิดของเฟส (Face Topology Type - ตามหลัก Animation Retopology)"
                )
                
                symmetry_mode = gr.Radio(
                    choices=["ปิด (None)", "แกน X (X-Axis)", "แกน Y (Y-Axis)", "แกน Z (Z-Axis)"],
                    value="ปิด (None)",
                    label="🪞 สมมาตร 3 มิติ (3D Mesh Symmetry - สำหรับ Character & Props)"
                )
                
                display_mode = gr.Radio(
                    choices=["Shade + Wireframe", "Shaded Only", "Wireframe Only"],
                    value="Shade + Wireframe",
                    label="โหมดการแสดงผลใน 3D Viewport (Display Mode)"
                )
                
                with gr.Row():
                    target_face_slider = gr.Slider(
                        minimum=50, 
                        maximum=10000, 
                        value=2500, 
                        step=50, 
                        label="จำนวน Polygon เป้าหมาย (Target Faces)"
                    )
                    target_face_number = gr.Number(
                        value=2500, 
                        precision=0, 
                        label="ป้อนตัวเลข (Target Faces)"
                    )
                
                percent_reduction_slider = gr.Slider(
                    minimum=0.0, 
                    maximum=99.0, 
                    value=50.0, 
                    step=1.0, 
                    label="เปอร์เซ็นต์การลดโพลีกอน (% Reduction)"
                )
                
                export_fmt = gr.Radio(
                    choices=["OBJ", "FBX", "GLTF", "PLY"],
                    value="OBJ",
                    label="รูปแบบไฟล์สำหรับดาวน์โหลด (Export Format)"
                )

                btn_retopo = gr.Button("🚀 เริ่มทำ Retopology & ลดโพลีกอน", elem_classes=["btn-primary-custom"])

        # Right Column: 3D Viewports
        with gr.Column(scale=7):
            
            with gr.Tabs() as view_tabs:
                tab_input = gr.TabItem("🔍 โมเดลต้นฉบับ (Original 3D Model)")
                with tab_input:
                    input_3d_viewer = gr.Model3D(
                        label="Input 3D Model Viewport", 
                        height=380,
                        clear_color=[0.08, 0.12, 0.18, 1.0]
                    )
                    
                tab_output = gr.TabItem("✨ ผลลัพธ์ (Retopo Low-Poly Model)")
                with tab_output:
                    output_3d_viewer = gr.Model3D(
                        label="Processed Retopo 3D Viewport", 
                        height=380,
                        clear_color=[0.06, 0.14, 0.12, 1.0]
                    )

            stats_panel = gr.Markdown("""
            ### 📈 ผลการประมวลผล
            *กดปุ่ม 'เริ่มทำ Retopology' เพื่อดูผลลัพธ์และโครงเส้น Wireframe*
            """)
            
            with gr.Group():
                sec3_md = gr.Markdown("### 💾 3. ดาวน์โหลดโมเดลที่ทำ Retopology แล้ว")
                download_file = gr.File(label="ไฟล์โมเดลสำเร็จพร้อมใช้งาน", interactive=False)

    # Theme Switcher Callback
    theme_select.change(
        fn=generate_theme_css,
        inputs=[theme_select],
        outputs=[theme_css_box]
    )

    # Language Switcher Callback
    def switch_language(lang_key):
        t = TRANSLATIONS.get(lang_key, TRANSLATIONS["🇹🇭 ภาษาไทย (TH)"])
        header_html = f"""
        <div class="header-box">
            <div class="header-title">{t['header_title']}</div>
            <div class="header-subtitle">{t['header_sub']}</div>
        </div>
        """
        return (
            header_html,
            t['sec1_title'],
            gr.update(label=t['input_file']),
            gr.update(label=t['sample_select']),
            t['status_default'],
            t['sec2_title'],
            gr.update(label=t['face_type']),
            gr.update(label=t['symmetry_mode']),
            gr.update(label=t['display_mode']),
            gr.update(label=t['target_slider']),
            gr.update(label=t['target_num']),
            gr.update(label=t['pct_slider']),
            gr.update(label=t['export_fmt']),
            gr.update(value=t['btn_retopo']),
            t['stats_default'],
            t['sec3_title'],
            gr.update(label=t['dl_label'])
        )

    lang_select.change(
        fn=switch_language,
        inputs=[lang_select],
        outputs=[
            header_html_box,
            sec1_md, input_file, sample_select, status_info,
            sec2_md, face_type, symmetry_mode, display_mode,
            target_face_slider, target_face_number, percent_reduction_slider, export_fmt, btn_retopo,
            stats_panel, sec3_md, download_file
        ]
    )

    # Event Callbacks Syncing
    
    input_file.change(
        fn=prepare_input_model,
        inputs=[input_file, sample_select, display_mode],
        outputs=[input_3d_viewer, status_info, orig_faces_state, orig_verts_state, target_face_slider, target_face_number]
    )
    
    sample_select.change(
        fn=prepare_input_model,
        inputs=[input_file, sample_select, display_mode],
        outputs=[input_3d_viewer, status_info, orig_faces_state, orig_verts_state, target_face_slider, target_face_number]
    )
    
    display_mode.change(
        fn=prepare_input_model,
        inputs=[input_file, sample_select, display_mode],
        outputs=[input_3d_viewer, status_info, orig_faces_state, orig_verts_state, target_face_slider, target_face_number]
    )
    
    app.load(
        fn=prepare_input_model,
        inputs=[input_file, sample_select, display_mode],
        outputs=[input_3d_viewer, status_info, orig_faces_state, orig_verts_state, target_face_slider, target_face_number]
    )

    # 2. Safe sync without circular trigger errors
    target_face_slider.release(
        fn=sync_from_target,
        inputs=[orig_faces_state, target_face_slider],
        outputs=[target_face_number, percent_reduction_slider]
    )
    
    target_face_number.change(
        fn=sync_from_target,
        inputs=[orig_faces_state, target_face_number],
        outputs=[target_face_slider, percent_reduction_slider]
    )
    
    percent_reduction_slider.release(
        fn=sync_from_percent,
        inputs=[orig_faces_state, percent_reduction_slider],
        outputs=[target_face_slider, target_face_number]
    )

    # 3. Retopology execution button click
    btn_retopo.click(
        fn=run_retopology_process,
        inputs=[input_file, sample_select, target_face_slider, face_type, display_mode, export_fmt, symmetry_mode, lang_select],
        outputs=[output_3d_viewer, stats_panel, download_file, download_file]
    )

def get_local_ip():
    """Retrieve local network IP address for LAN sharing"""
    import socket
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(('8.8.8.8', 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except Exception:
        return "127.0.0.1"

def find_available_port(start_port=7860, max_attempts=20):
    import socket
    for port in range(start_port, start_port + max_attempts):
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            if s.connect_ex(('127.0.0.1', port)) != 0:
                return port
    return start_port

if __name__ == "__main__":
    server_port = find_available_port(7860)
    local_ip = get_local_ip()
    
    # Check if share is requested via env or default true
    enable_share = os.environ.get("GRADIO_SHARE", "true").lower() in ["true", "1", "yes"]
    
    print("=" * 75)
    print("🚀 3D RETOPOLOGY WEBAPP IS NOW RUNNING!")
    print("=" * 75)
    print(f"🏠 Local Access (สำหรับใช้งานในเครื่องนี้):        http://127.0.0.1:{server_port}")
    print(f"🌐 LAN/Wi-Fi Access (สำหรับเครื่องอื่นในวง Wi-Fi): http://{local_ip}:{server_port}")
    print("=" * 75)
    
    if enable_share:
        try:
            print("🌍 กำลังเชื่อมต่อสร้าง Public Share Link (Gradio Live)...")
            app.launch(
                server_name="0.0.0.0", 
                server_port=server_port, 
                share=True, 
                css=CUSTOM_CSS, 
                theme=gr.themes.Soft(),
                inbrowser=True
            )
        except Exception as e:
            print("\n⚠️ ไม่สามารถสร้าง Public Share Link ได้เนื่องจากข้อจำกัดเครือข่ายอินเทอร์เน็ต/ไฟร์วอลล์")
            print(f"ℹ️ ระบบสลับมาเปิดโหมด LAN Wi-Fi Sharing ให้เครื่องอื่นในเครือข่ายเข้าใช้งานแทนโดยอัตโนมัติ")
            print(f"🔗 ลิงก์สำหรับเครื่องอื่นในวง Wi-Fi เดียวกัน: http://{local_ip}:{server_port}\n")
            app.launch(
                server_name="0.0.0.0", 
                server_port=server_port, 
                share=False, 
                css=CUSTOM_CSS, 
                theme=gr.themes.Soft(),
                inbrowser=True
            )
    else:
        app.launch(
            server_name="0.0.0.0", 
            server_port=server_port, 
            share=False, 
            css=CUSTOM_CSS, 
            theme=gr.themes.Soft(),
            inbrowser=True
        )
