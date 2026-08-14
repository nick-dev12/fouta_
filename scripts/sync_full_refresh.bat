@echo off
cd /d "%~dp0.."
echo.
echo ========================================
echo  Fouta - REFRESH COMPLET Production
echo  production -^> WAMP -^> serveur local
echo ========================================
echo.
for %%P in ("C:\wamp64\bin\php\php8.3.14\php.exe" "C:\wamp64\bin\php\php8.2.26\php.exe") do if not defined PHP_EXE if exist %%P set PHP_EXE=%%~P
if not defined PHP_EXE set PHP_EXE=php
echo PHP : %PHP_EXE%
"%PHP_EXE%" -r "if (!function_exists('ftp_connect')) { fwrite(STDERR, 'ERREUR: extension PHP ftp absente. Activez extension=ftp dans php.ini puis redemarrez WAMP.'.PHP_EOL); exit(1); }"
if errorlevel 1 (
  echo.
  pause
  exit /b 1
)
"%PHP_EXE%" -d output_buffering=0 -d implicit_flush=1 scripts\sync_prod_to_wamp_and_server.php --full
echo.
pause
