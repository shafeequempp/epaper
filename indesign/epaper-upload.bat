@echo off
rem ============================================================================
rem  ePaper one-click upload
rem
rem  COPY THIS FILE into the folder that holds the day's .indd pages
rem  (the folder that also contains the "Links" subfolder), then double-click it.
rem
rem  It opens InDesign, exports every .indd in this folder, and uploads the pages
rem  to the ePaper dashboard with TODAY as the date. Editions are detected from the
rem  file names (e.g. 12pg2wd -> Wayanad). The pages then wait in the dashboard for
rem  an editor to review and publish.
rem ============================================================================

setlocal

rem --- where the ePaper driver is installed (edit this if you move the install) ---
set "DRIVER=C:\wamp64\www\epaper\indesign\epaper-run.vbs"

rem --- the folder this .bat is sitting in (the .indd folder) ---
set "PAGEDIR=%~dp0"

rem --- a success marker: the driver writes it ONLY if the run fully succeeds ---
set "STATUS=%TEMP%\epaper_status_%RANDOM%%RANDOM%.txt"
if exist "%STATUS%" del "%STATUS%" >nul 2>&1

if not exist "%DRIVER%" (
  echo.
  echo Cannot find the ePaper driver at:
  echo    %DRIVER%
  echo.
  echo Edit this .bat file and set DRIVER to the correct path.
  echo.
  pause
  exit /b 1
)

echo.
echo ePaper upload - folder:
echo    %PAGEDIR%
echo.

cscript //nologo "%DRIVER%" "%PAGEDIR%." "%STATUS%"

echo.
if exist "%STATUS%" (
  del "%STATUS%" >nul 2>&1
  echo Finished. Open  http://localhost/epaper/admin/  to review and publish.
) else (
  echo There was a PROBLEM - the upload did not complete. See the message above.
)
echo.
pause
endlocal
