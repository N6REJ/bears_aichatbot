@echo off
setlocal EnableExtensions

REM Link builder for Bears AI Chatbot package
REM Run this script in an elevated (Administrator) Command Prompt.

REM === Configure paths ===
set "SRC_BASE=E:\MY_PROJECTS\bears_aichatbot"
set "TARGET_BASE=E:\MY_PROJECTS\Bearsampp\www\bearsampp"

REM === Helpers ===
:linkDir
REM %1 = link path (target), %2 = source directory
set "LINK=%~1"
set "SRC=%~2"
for %%I in ("%LINK%") do set "PARENT=%%~dpI"
if not exist "%PARENT%" (
  echo [+] Creating parent directory: "%PARENT%"
  mkdir "%PARENT%" >nul 2>&1
)
if exist "%LINK%\NUL" (
  echo [*] Removing existing directory at "%LINK%"
  rmdir /S /Q "%LINK%"
) else if exist "%LINK%" (
  echo [*] Removing existing file at "%LINK%"
  del /F /Q "%LINK%"
)
if not exist "%SRC%\NUL" (
  echo [!] ERROR: Source directory not found: "%SRC%"
  exit /b 1
)
echo [>] mklink /D "%LINK%" "%SRC%"
mklink /D "%LINK%" "%SRC%"
exit /b 0

:linkFile
REM %1 = link path (target), %2 = source file
set "LINK=%~1"
set "SRC=%~2"
for %%I in ("%LINK%") do set "PARENT=%%~dpI"
if not exist "%PARENT%" (
  echo [+] Creating parent directory: "%PARENT%"
  mkdir "%PARENT%" >nul 2>&1
)
if exist "%LINK%\NUL" (
  echo [*] Removing existing directory at file path "%LINK%"
  rmdir /S /Q "%LINK%"
) else if exist "%LINK%" (
  echo [*] Removing existing file/link "%LINK%"
  del /F /Q "%LINK%"
)
if not exist "%SRC%" (
  echo [!] WARNING: Source file not found (skipping): "%SRC%"
  exit /b 0
)
echo [>] mklink "%LINK%" "%SRC%"
mklink "%LINK%" "%SRC%"
exit /b 0

REM === Create directory links for each package part ===
call :linkDir "%TARGET_BASE%\administrator\components\com_bears_aichatbot" "%SRC_BASE%\com_bears_aichatbot"
call :linkDir "%TARGET_BASE%\modules\mod_bears_aichatbot" "%SRC_BASE%\mod_bears_aichatbot"
call :linkDir "%TARGET_BASE%\plugins\task\bears_aichatbot" "%SRC_BASE%\plugins\task\bears_aichatbot"
call :linkDir "%TARGET_BASE%\plugins\content\bears_aichatbot" "%SRC_BASE%\plugins\content\bears_aichatbot"
call :linkDir "%TARGET_BASE%\plugins\system\bears_aichatbotinstaller" "%SRC_BASE%\plugins\system\bears_aichatbotinstaller"

REM === Language file links (component admin language) ===
call :linkFile "%TARGET_BASE%\administrator\language\en-GB\en-GB.com_bears_aichatbot.ini" "%SRC_BASE%\com_bears_aichatbot\language\en-GB\en-GB.com_bears_aichatbot.ini"
call :linkFile "%TARGET_BASE%\administrator\language\en-GB\en-GB.com_bears_aichatbot.sys.ini" "%SRC_BASE%\com_bears_aichatbot\language\en-GB\en-GB.com_bears_aichatbot.sys.ini"
call :linkFile "%TARGET_BASE%\administrator\language\en-GB\en-GB.mod_bears_aichatbot.ini" "%SRC_BASE%\mod_bears_aichatbot\language\en-GB\en-GB.mod_bears_aichatbot.ini"
call :linkFile "%TARGET_BASE%\administrator\language\en-GB\en-GB.mod_bears_aichatbot.sys.ini" "%SRC_BASE%\mod_bears_aichatbot\language\en-GB\en-GB.mod_bears_aichatbot.sys.ini"
call :linkFile "%TARGET_BASE%\administrator\language\en-GB\en-GB.plg_content_bears_aichatbot.ini" "%SRC_BASE%E:\plugins\content\bears_aichatbot\language\en-GBen-GB.plg_content_bears_aichatbot.ini"
call :linkFile "%TARGET_BASE%\administrator\language\en-GB\en-GB.plg_content_bears_aichatbot.sys.ini" "%SRC_BASE%\plugins\content\bears_aichatbot\language\en-GB\en-GB.plg_content_bears_aichatbot.sys.ini"
call :linkFile "%TARGET_BASE%\administrator\language\en-GB\en-GB.plg_task_bears_aichatbot.ini" "%SRC_BASE%E:\plugins\task\bears_aichatbot\language\en-GBen-GB.plg_task_bears_aichatbot.ini"
call :linkFile "%TARGET_BASE%\administrator\language\en-GB\en-GB.plg_task_bears_aichatbot.sys.ini" "%SRC_BASE%\plugins\task\bears_aichatbot\language\en-GB\en-GB.plg_task_bears_aichatbot.sys.ini"

echo.
echo [DONE] Symlink setup complete.
exit /b 0
