@echo off
start "" "C:\xampp\mysql\bin\mysqld.exe"
timeout /t 3
start php artisan serve --host=0.0.0.0 --port=8000
timeout /t 3
ngrok http --host-header="localhost:8000" 8000