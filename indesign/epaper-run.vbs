' ============================================================================
'  epaper-run.vbs  —  one-click ePaper export + upload driver
'
'  Starts Adobe InDesign, runs EpaperExport.jsx (which lives next to this file)
'  over every .indd in a folder, with TODAY as the issue date and upload ON, then
'  the pages wait in the dashboard for an editor to review and publish.
'
'  Usage (normally called by epaper-upload.bat):
'     cscript //nologo epaper-run.vbs "C:\path\to\the\indd\folder" ["statusfile"]
'  If no folder is given, the folder this script sits in is used.
'  If a status file path is given, it is written ONLY when the run fully
'  succeeds, so the caller can tell success from failure reliably.
' ============================================================================
Option Explicit

Dim fso
Set fso = CreateObject("Scripting.FileSystemObject")

' ---- optional success-marker file (written only on full success) ---------
Dim statusFile
statusFile = ""
If WScript.Arguments.Count >= 2 Then statusFile = WScript.Arguments(1)

' ---- which folder of .indd pages ----------------------------------------
Dim pageDir
If WScript.Arguments.Count >= 1 Then
    pageDir = fso.GetAbsolutePathName(WScript.Arguments(0))
Else
    pageDir = fso.GetParentFolderName(WScript.ScriptFullName)
End If
If Not fso.FolderExists(pageDir) Then Fail "Folder not found:" & vbCrLf & pageDir

' ---- find the export script (sits beside this driver) --------------------
Dim jsxPath
jsxPath = fso.BuildPath(fso.GetParentFolderName(WScript.ScriptFullName), "EpaperExport.jsx")
If Not fso.FileExists(jsxPath) Then Fail "EpaperExport.jsx was not found next to this script:" & vbCrLf & jsxPath

' ---- today's date as YYYY-MM-DD -----------------------------------------
Dim today
today = Year(Date) & "-" & Pad2(Month(Date)) & "-" & Pad2(Day(Date))

' ---- read the script's code ---------------------------------------------
Dim code, ts
Set ts = fso.OpenTextFile(jsxPath, 1)   ' 1 = ForReading
If Not ts.AtEndOfStream Then code = ts.ReadAll Else code = ""
ts.Close
If Len(code) = 0 Then Fail "EpaperExport.jsx is empty: " & jsxPath

' ---- connect to InDesign (start it if needed) ---------------------------
WScript.Echo "ePaper: starting InDesign..."
Dim idApp
Set idApp = GetInDesign()
If idApp Is Nothing Then
    Fail "Could not start Adobe InDesign." & vbCrLf & _
         "Make sure InDesign is installed on this PC and can be opened normally." & vbCrLf & _
         "Tried these COM names: " & TriedProgIDs
End If
On Error Resume Next
idApp.Visible = True
On Error GoTo 0

' ---- run the export, no dialog: [folder, date, upload, publish] ----------
Dim args
args = Array(pageDir, today, "upload", "nopublish")

Const idJavascript = 1246973031   ' ScriptLanguage.JAVASCRIPT

WScript.Echo "ePaper: exporting and uploading pages for " & today & " ..."
WScript.Echo "         folder: " & pageDir
On Error Resume Next
idApp.DoScript code, idJavascript, args
If Err.Number <> 0 Then
    Dim m : m = Err.Description
    On Error GoTo 0
    Fail "InDesign could not run the export:" & vbCrLf & m
End If
On Error GoTo 0

' ---- mark success so the caller knows it really worked -------------------
WriteStatus
WScript.Echo "ePaper: done. Open the dashboard to review and publish."
WScript.Quit 0

' ============================================================ helpers

Dim gTried
gTried = ""

Function TriedProgIDs()
    TriedProgIDs = gTried
End Function

Function GetInDesign()
    Dim app, ids, id, i
    Set GetInDesign = Nothing

    ' 1) reuse an already-open InDesign, if any
    Set app = Nothing
    On Error Resume Next
    Set app = GetObject(, "InDesign.Application")
    On Error GoTo 0
    If Not (app Is Nothing) Then
        Set GetInDesign = app : Exit Function
    End If

    ' 2) otherwise launch one. Build the list of COM names to try:
    '    registry-discovered first (highest version), then generic + fallbacks.
    ids = BuildProgIDList()
    For i = 0 To UBound(ids)
        id = ids(i)
        If Len(id) > 0 Then
            If Len(gTried) > 0 Then gTried = gTried & ", "
            gTried = gTried & id
            Set app = Nothing
            On Error Resume Next
            Set app = CreateObject(id)
            On Error GoTo 0
            If Not (app Is Nothing) Then
                Set GetInDesign = app : Exit Function
            End If
        End If
    Next
End Function

' Returns an array of InDesign COM ProgIDs to try, best first. It probes the
' registry for the versions actually installed (newest first), then adds the
' version-independent name and a hardcoded range as a safety net.
Function BuildProgIDList()
    Dim seen, out, n, y, id
    Set seen = CreateObject("Scripting.Dictionary")
    seen.CompareMode = 1   ' case-insensitive
    ReDim out(400)
    n = 0

    ' --- installed year-versions, newest first (probe a generous range) ----
    For y = 2035 To 2018 Step -1
        id = "InDesign.Application." & y
        If ProgIDExists(id) And Not seen.Exists(id) Then
            seen.Add id, True : out(n) = id : n = n + 1
        End If
    Next
    ' --- older "CC.YYYY" naming -------------------------------------------
    For y = 2019 To 2014 Step -1
        id = "InDesign.Application.CC." & y
        If ProgIDExists(id) And Not seen.Exists(id) Then
            seen.Add id, True : out(n) = id : n = n + 1
        End If
    Next

    ' --- version-independent name (points to the default install) ----------
    If Not seen.Exists("InDesign.Application") Then
        seen.Add "InDesign.Application", True
        out(n) = "InDesign.Application" : n = n + 1
    End If

    ' --- hardcoded fallbacks in case registry probing is blocked -----------
    Dim fb, k
    fb = Array( _
        "InDesign.Application.2030", "InDesign.Application.2029", _
        "InDesign.Application.2028", "InDesign.Application.2027", _
        "InDesign.Application.2026", "InDesign.Application.2025", _
        "InDesign.Application.2024", "InDesign.Application.2023", _
        "InDesign.Application.2022", "InDesign.Application.2021", _
        "InDesign.Application.2020", _
        "InDesign.Application.CC.2019", "InDesign.Application.CC.2018")
    For k = 0 To UBound(fb)
        If Not seen.Exists(fb(k)) Then
            seen.Add fb(k), True : out(n) = fb(k) : n = n + 1
        End If
    Next

    ReDim Preserve out(n - 1)
    BuildProgIDList = out
End Function

' True if the given COM ProgID is registered (reads its default value).
Function ProgIDExists(progid)
    On Error Resume Next
    Dim sh, v
    Set sh = CreateObject("WScript.Shell")
    v = sh.RegRead("HKCR\" & progid & "\")
    ProgIDExists = (Err.Number = 0)
    On Error GoTo 0
End Function

Sub WriteStatus()
    If Len(statusFile) = 0 Then Exit Sub
    On Error Resume Next
    Dim f
    Set f = fso.CreateTextFile(statusFile, True)
    f.WriteLine "OK"
    f.Close
    On Error GoTo 0
End Sub

Function Pad2(n)
    If n < 10 Then Pad2 = "0" & n Else Pad2 = CStr(n)
End Function

Sub Fail(msg)
    WScript.Echo "ePaper ERROR: " & msg
    WScript.Quit 1
End Sub
