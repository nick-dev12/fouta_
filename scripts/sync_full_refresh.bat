@echo off
cd /d "%~dp0.."
echo.
echo ========================================
echo  Fouta - REFRESH COMPLET Production
echo  production -^> WAMP -^> serveur local
echo ========================================
echo.
for %%P in ("C:\wamp64\bin\php\php8.3.14\php.exe" "C:\wamp64\bin\php\php8.2.26\php.exe") do if exist %%P set PHP_EXE=%%~P
if not defined PHP_EXE set PHP_EXE=php
"%PHP_EXE%" scripts\sync_prod_to_wamp_and_server.php --full
echo.
pause
