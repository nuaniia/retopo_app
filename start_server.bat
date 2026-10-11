@echo off
title RETOPO.AI 3D Suite - Local Server
cd /d "%~dp0"
echo ================================================================
echo   Launching RETOPO.AI 3D Suite Local Web Server...
echo ================================================================
powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process 'http://localhost:8080/'; & { $l = New-Object System.Net.HttpListener; $l.Prefixes.Add('http://localhost:8080/'); $l.Start(); while($l.IsListening){ $c = $l.GetContext(); $p = $c.Request.Url.LocalPath; if($p -eq '/') { $p = '/index.html' }; $f = Join-Path '.' ($p.TrimStart('/')); if(Test-Path $f) { $b = [System.IO.File]::ReadAllBytes($f); $c.Response.OutputStream.Write($b, 0, $b.Length) } else { $c.Response.StatusCode = 404 }; $c.Response.Close() } }"
pause