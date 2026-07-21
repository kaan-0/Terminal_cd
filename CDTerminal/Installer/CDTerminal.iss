#ifndef MyAppVersion
  #define MyAppVersion "1.1.0"
#endif

#define MyAppName "CD Terminal"
#define MyAppPublisher "Circuitos y Desarrollos en Tecnología"
#define MyAppExeName "CDTerminal.exe"
#define MyAppSourceDir "..\artifacts\publish\win-x64"
#define MyAppIcon "..\Assets\CDTerminal.ico"
#define WebView2Installer "Dependencies\MicrosoftEdgeWebView2RuntimeInstallerX64.exe"

[Setup]
; Mantener este AppId permite actualizar la instalación 1.0.0 existente.
AppId={{A1ED66B4-C983-46F9-98A9-E90D1AADE52D}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppVerName={#MyAppName} {#MyAppVersion}
AppPublisher={#MyAppPublisher}
VersionInfoVersion={#MyAppVersion}.0
VersionInfoCompany={#MyAppPublisher}
VersionInfoDescription=Instalador de {#MyAppName}
VersionInfoProductName={#MyAppName}

DefaultDirName={localappdata}\Programs\CD Terminal
DefaultGroupName=CD Terminal
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
PrivilegesRequiredOverridesAllowed=dialog

ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
MinVersion=10.0

OutputDir=..\artifacts\installer
OutputBaseFilename=CDTerminal-Setup-{#MyAppVersion}-x64
SetupIconFile={#MyAppIcon}
UninstallDisplayIcon={app}\{#MyAppExeName}

Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
CloseApplications=yes
RestartApplications=no
ChangesEnvironment=no
DisableReadyPage=no
DisableFinishedPage=no

[Languages]
Name: "spanish"; MessagesFile: "compiler:Languages\Spanish.isl"

[Tasks]
Name: "desktopicon"; Description: "Crear acceso directo en el escritorio"; GroupDescription: "Accesos directos:"; Flags: unchecked

[Files]
Source: "{#MyAppSourceDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#WebView2Installer}"; DestDir: "{tmp}"; DestName: "MicrosoftEdgeWebView2RuntimeInstallerX64.exe"; Flags: deleteafterinstall skipifsourcedoesntexist

[Icons]
Name: "{autoprograms}\CD Terminal"; Filename: "{app}\{#MyAppExeName}"; WorkingDir: "{app}"
Name: "{autodesktop}\CD Terminal"; Filename: "{app}\{#MyAppExeName}"; WorkingDir: "{app}"; Tasks: desktopicon

[Run]
Filename: "{tmp}\MicrosoftEdgeWebView2RuntimeInstallerX64.exe"; Parameters: "/silent /install"; StatusMsg: "Instalando Microsoft Edge WebView2 Runtime..."; Flags: waituntilterminated; Check: (not IsWebView2Installed) and FileExists(ExpandConstant('{tmp}\MicrosoftEdgeWebView2RuntimeInstallerX64.exe'))
Filename: "{app}\{#MyAppExeName}"; Description: "Abrir CD Terminal"; Flags: nowait postinstall skipifsilent; Check: IsWebView2Installed

[Code]
const
  WebView2ClientId = '{F3017226-FE2A-4295-8BDF-00C3A9A7E4C5}';

function IsValidWebView2Version(const Version: string): Boolean;
begin
  Result := (Version <> '') and (Version <> '0.0.0.0');
end;

function IsWebView2Installed: Boolean;
var
  Version: string;
begin
  Result := False;

  if RegQueryStringValue(
       HKLM64,
       'SOFTWARE\WOW6432Node\Microsoft\EdgeUpdate\Clients\' + WebView2ClientId,
       'pv',
       Version) then
  begin
    if IsValidWebView2Version(Version) then
    begin
      Result := True;
      Exit;
    end;
  end;

  if RegQueryStringValue(
       HKCU,
       'Software\Microsoft\EdgeUpdate\Clients\' + WebView2ClientId,
       'pv',
       Version) then
  begin
    Result := IsValidWebView2Version(Version);
  end;
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if (CurStep = ssPostInstall) and (not IsWebView2Installed) then
  begin
    MsgBox(
      'CD Terminal se instaló correctamente, pero Microsoft Edge WebView2 Runtime no está disponible.' + #13#10 + #13#10 +
      'Instala WebView2 Runtime antes de abrir el programa. Para crear un instalador totalmente offline, coloca el instalador x64 de WebView2 dentro de Installer\Dependencies y vuelve a compilar.',
      mbInformation,
      MB_OK);
  end;
end;
