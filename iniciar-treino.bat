@echo off
setlocal

set "APP_DIR=%~dp0treino-estrategia-nerd"

if not exist "%APP_DIR%" (
  echo Pasta do app de treino nao encontrada:
  echo %APP_DIR%
  pause
  exit /b 1
)

cd /d "%APP_DIR%"

if not exist ".env" (
  echo Arquivo .env nao encontrado.
  echo Criando .env a partir de env.example...
  copy "env.example" ".env" >nul
  echo.
)

if not exist "node_modules" (
  echo Instalando dependencias...
  npm install
  if errorlevel 1 (
    echo.
    echo Nao foi possivel instalar as dependencias.
    pause
    exit /b 1
  )
)

echo.
echo Iniciando Treino Estrategia Nerd...
echo Acesse: http://localhost:3000
echo.
start "" "http://localhost:3000"
npm run dev

endlocal
exit /b 0
