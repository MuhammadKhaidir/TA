@echo off
setlocal
echo ==========================================
echo  TA Prototype - First Setup
echo ==========================================

where php >nul 2>nul || (
  echo PHP tidak ditemukan. Pastikan PHP ada di PATH.
  exit /b 1
)
where composer >nul 2>nul || (
  echo Composer tidak ditemukan. Pastikan Composer ada di PATH.
  exit /b 1
)

if not exist .env copy .env.example .env
if not exist database\database.sqlite type nul > database\database.sqlite

echo [1/5] composer install...
call composer install || exit /b 1

echo [2/5] application key...
call php artisan key:generate || exit /b 1

echo [3/5] database migrate + demo seed...
call php artisan migrate:fresh --seed || exit /b 1

echo [4/5] frontend...
call npm install || exit /b 1
call npm run build || exit /b 1

echo [5/5] storage link...
call php artisan storage:link

echo.
echo Selesai.
echo Jalankan: php artisan serve
echo Admin/internal: http://127.0.0.1:8000/admin
pause
