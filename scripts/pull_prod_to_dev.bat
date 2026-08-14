@echo off
setlocal
cd /d "%~dp0.."

set PHP_EXE=
for %%P in (
  "C:\wamp64\bin\php\php8.4.0\php.exe"
  "C:\wamp64\bin\php\php8.3.14\php.exe"
  "C:\wamp64\bin\php\php8.2.26\php.exe"
  "C:\wamp64\bin\php\php8.1.31\php.exe"
) do if exist %%P if not defined PHP_EXE set PHP_EXE=%%~P

if "%PHP_EXE%"=="" (
  where php >nul 2>&1
  if errorlevel 1 (
    echo PHP introuvable. Installez WAMP ou ajoutez php au PATH.
    pause
    exit /b 1
  )
  set PHP_EXE=php
)

echo.
echo ========================================
echo  Fouta - Production vers DEVELOPPEMENT
echo  BDD + dossier upload/ depuis le VPS
echo ========================================
echo.
echo PHP : %PHP_EXE%
echo.

"%PHP_EXE%" -d output_buffering=0 -d implicit_flush=1 scripts\pull_prod_to_dev.php %*

echo.
pause
