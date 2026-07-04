; Unitec ERP Web — instalador Windows (Inno Setup)
; Compilar apos: .\scripts\build-setup.ps1

#define MyAppName "UNI SISTEMAS 3.0"
#define MyAppVersion "6.4.1.36"
#define MyAppVerName "UNI SISTEMAS 3.0"
#define MyAppPublisher "UNITECNOLOGIA"
#define MyAppURL "https://unitecnologiasc.com.br/"
#define MyAppDir "C:\UNITECNOLOGIA_WEB"
#define MyStagingDir "..\dist\staging\unitec-erp-web"
#define SiteBase "http://127.0.0.1:8765"
#define LauncherUnitec "Unitec ERP.bat"
#define LauncherRetaguarda "INFORSYSTEM Retaguarda.bat"
#define LauncherPdv "INFORSYSTEM PDV.bat"
#define LauncherPreVenda "INFORSYSTEM Pre-venda.bat"
#define MyAppIcon "assets\unitec-erp.ico"

[Setup]
AppId={{918F7651-7407-476D-BE91-3C95AD6B538D}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppVerName={#MyAppVerName}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName={#MyAppDir}
DisableDirPage=yes
DefaultGroupName=UNITECNOLOGIA 6
DisableProgramGroupPage=yes
OutputDir=..\dist\output
OutputBaseFilename=Instalar Unitec ERP
Compression=lzma2/ultra64
SolidCompression=yes
PrivilegesRequired=admin
WizardStyle=modern
ArchitecturesInstallIn64BitMode=x64compatible
MinVersion=10.0
DisableWelcomePage=no
SetupIconFile={#MyAppIcon}

[Languages]
Name: "brazilianportuguese"; MessagesFile: "compiler:Languages\BrazilianPortuguese.isl"

[Tasks]
Name: "desktopicon"; Description: "Criar atalho &""Unitec ERP"" na Area de Trabalho"; GroupDescription: "Atalhos:"; Flags: checkedonce

[Files]
Source: "{#MyStagingDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: ".env,public\storage"

[Icons]
Name: "{group}\Unitec ERP"; Filename: "{app}\{#LauncherUnitec}"; WorkingDir: "{app}"; IconFilename: "{app}\installer\{#MyAppIcon}"; Comment: "Abrir o sistema"
Name: "{group}\INFORSYSTEM Retaguarda"; Filename: "{app}\{#LauncherRetaguarda}"; WorkingDir: "{app}"; IconFilename: "{app}\installer\{#MyAppIcon}"
Name: "{group}\INFORSYSTEM PDV"; Filename: "{app}\{#LauncherPdv}"; WorkingDir: "{app}"; IconFilename: "{app}\installer\{#MyAppIcon}"
Name: "{group}\INFORSYSTEM Pre-venda"; Filename: "{app}\{#LauncherPreVenda}"; WorkingDir: "{app}"; IconFilename: "{app}\installer\{#MyAppIcon}"
Name: "{group}\{cm:ProgramOnTheWeb,{#MyAppName}}"; Filename: "{#MyAppURL}"
Name: "{group}\{cm:UninstallProgram,{#MyAppName}}"; Filename: "{uninstallexe}"
Name: "{commondesktop}\Unitec ERP"; Filename: "{app}\{#LauncherUnitec}"; WorkingDir: "{app}"; IconFilename: "{app}\installer\{#MyAppIcon}"; Tasks: desktopicon; Comment: "Abrir o Unitec ERP"

[UninstallDelete]
Type: filesandordirs; Name: "{app}"

[Messages]
brazilianportuguese.WelcomeLabel2=Este assistente instala o Unitec ERP ({#MyAppVerName}).%n%nVoce so precisa clicar em Avancar e Instalar.%n%nO sistema abrira sozinho ao terminar.%n%nRequisitos: Windows 10 ou superior, 64 bits, 2 GB livres em C:
brazilianportuguese.FinishedLabel=Instalacao concluida!%n%nUse o atalho "Unitec ERP" na Area de Trabalho.%n%nLogin:%n  E-mail: usuario@unitecnologia.local%n  Senha: 01

[Code]
var
  DbModePage: TWizardPage;
  DbServerRadio: TNewRadioButton;
  DbTerminalRadio: TNewRadioButton;
  DbHostLabel: TNewStaticText;
  DbHostEdit: TNewEdit;
  SelectedDbHost: String;

function InitializeSetup(): Boolean;
var
  FreeMB, TotalMB: Cardinal;
begin
  Result := True;
  SelectedDbHost := '127.0.0.1';

  if not IsWin64 then
  begin
    MsgBox('Este instalador funciona apenas em Windows 64 bits.', mbCriticalError, MB_OK);
    Result := False;
    Exit;
  end;

  if GetSpaceOnDisk('C:\', True, FreeMB, TotalMB) then
  begin
    if FreeMB < 2048 then
    begin
      MsgBox('Espaco insuficiente em disco.' + #13#10 + #13#10 + 'Libere pelo menos 2 GB em C: e tente novamente.', mbError, MB_OK);
      Result := False;
      Exit;
    end;
  end;
end;

procedure DbModeChanged(Sender: TObject);
begin
  DbHostEdit.Enabled := DbTerminalRadio.Checked;
  DbHostLabel.Enabled := DbTerminalRadio.Checked;
end;

procedure InitializeWizard();
begin
  DbModePage := CreateCustomPage(wpSelectTasks, 'Banco de dados', 'Escolha se este computador guarda o banco ou se e um terminal da loja.');

  DbServerRadio := TNewRadioButton.Create(DbModePage);
  DbServerRadio.Parent := DbModePage.Surface;
  DbServerRadio.Left := 0;
  DbServerRadio.Top := 0;
  DbServerRadio.Width := DbModePage.SurfaceWidth;
  DbServerRadio.Caption := 'Servidor do banco (este PC) — MariaDB na rede local, porta 3306';
  DbServerRadio.Checked := True;
  DbServerRadio.OnClick := @DbModeChanged;

  DbTerminalRadio := TNewRadioButton.Create(DbModePage);
  DbTerminalRadio.Parent := DbModePage.Surface;
  DbTerminalRadio.Left := 0;
  DbTerminalRadio.Top := 28;
  DbTerminalRadio.Width := DbModePage.SurfaceWidth;
  DbTerminalRadio.Caption := 'Terminal (banco em outro PC da rede)';
  DbTerminalRadio.OnClick := @DbModeChanged;

  DbHostLabel := TNewStaticText.Create(DbModePage);
  DbHostLabel.Parent := DbModePage.Surface;
  DbHostLabel.Left := 24;
  DbHostLabel.Top := 58;
  DbHostLabel.Width := DbModePage.SurfaceWidth - 24;
  DbHostLabel.Caption := 'IP do servidor de banco (ex.: 192.168.0.52):';
  DbHostLabel.Enabled := False;

  DbHostEdit := TNewEdit.Create(DbModePage);
  DbHostEdit.Parent := DbModePage.Surface;
  DbHostEdit.Left := 24;
  DbHostEdit.Top := 78;
  DbHostEdit.Width := 220;
  DbHostEdit.Text := '192.168.0.52';
  DbHostEdit.Enabled := False;
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;

  if CurPageID = DbModePage.ID then
  begin
    if DbTerminalRadio.Checked then
    begin
      if Trim(DbHostEdit.Text) = '' then
      begin
        MsgBox('Informe o IP do servidor de banco (ex.: 192.168.0.52).', mbError, MB_OK);
        Result := False;
        Exit;
      end;
      SelectedDbHost := Trim(DbHostEdit.Text);
    end
    else
      SelectedDbHost := '127.0.0.1';
  end;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  ResultCode: Integer;
  Params: String;
begin
  if CurStep = ssPostInstall then
  begin
    Params := '-Sta -WindowStyle Hidden -NoProfile -ExecutionPolicy Bypass -File "' + ExpandConstant('{app}\scripts\instalar-tudo.ps1') + '" -NoPause -FromSetup -AppPath "' + ExpandConstant('{app}') + '" -AppUrl "{#SiteBase}" -DbHost "' + SelectedDbHost + '"';

    if not Exec('powershell.exe', Params, ExpandConstant('{app}'), SW_HIDE, ewWaitUntilTerminated, ResultCode) then
    begin
      MsgBox('Nao foi possivel concluir a instalacao automatica.' + #13#10 + #13#10 + 'Entre em contato com o suporte da Unitecnologia.', mbCriticalError, MB_OK);
      Abort;
    end;

    if ResultCode <> 0 then
    begin
      Abort;
    end;
  end;
end;
