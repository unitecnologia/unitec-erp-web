; Unitec ERP Web - instalador Windows (Inno Setup)
; Compilar apos: .\scripts\build-setup.ps1
;
; Fluxo:
; - Pasta nova (sem .env / sem tools\mysql\data) → instalação do zero
; - Pasta já existente → recupera (atualiza arquivos, NÃO apaga o banco)
;
; Atalhos: somente bin\Unitec ERP.exe
; Auto-start: servico Windows UnitecErpServer (Automatic)
; Sem PowerShell no atalho do cliente.

#define MyAppName "UNI SISTEMAS 3.0"
#define MyAppVersion "6.4.1.164"
#define MyAppVerName "UNI SISTEMAS 3.0"
#define MyAppPublisher "UNITECNOLOGIA"
#define MyAppURL "https://unitecnologiasc.com.br/"
#define MyAppDir "C:\UNITECNOLOGIA_WEB"
#define MyStagingDir "..\dist\staging\unitec-erp-web"
#define SiteBase "http://127.0.0.1:8765"
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

; Sem task de atalho no Inno — evita duplicar com New-UnitecDesktopShortcuts.

[Files]
; Nao sobrescreve dados do cliente em pacote padrao.
; Se o build incluir installer\seed (IncludeDevData), o .env vem no staging e sera aplicado.
Source: "{#MyStagingDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: ".env.backup,.env.production,public\storage"

[Icons]
; Menu Iniciar: somente Unitec ERP.exe (servico Windows cuida do stack).
; Area de Trabalho: criada so pelo scripts\unitec-install-lib.ps1 (New-UnitecDesktopShortcuts).
Name: "{group}\Unitec ERP"; Filename: "{app}\bin\Unitec ERP.exe"; Parameters: "--app ""{app}"""; WorkingDir: "{app}"; IconFilename: "{app}\installer\{#MyAppIcon}"; Comment: "Abrir Unitec ERP"
Name: "{group}\{cm:ProgramOnTheWeb,{#MyAppName}}"; Filename: "{#MyAppURL}"
Name: "{group}\{cm:UninstallProgram,{#MyAppName}}"; Filename: "{uninstallexe}"

; Nao apaga a pasta inteira no desinstalar — preserva banco (tools\mysql\data) e .env.
[UninstallDelete]
Type: files; Name: "{app}\.unitec-serve.pid"

[Messages]
brazilianportuguese.WelcomeLabel2=Este assistente instala o Unitec ERP ({#MyAppVerName}).%n%nSe a pasta C:\UNITECNOLOGIA_WEB ja existir, o instalador RECUPERA o sistema sem apagar o banco de dados.%n%nSe for a primeira vez, instala do zero.%n%nO sistema abre em janela de aplicativo (Chrome/Edge).%n%nRequisitos: Windows 10 ou superior, 64 bits, 2 GB livres em C:
brazilianportuguese.FinishedLabel=Instalacao concluida!%n%nUse o atalho "Unitec ERP" na Area de Trabalho.%n%nLogin (instalacao nova):%n  Usuario: USUARIO%n  Senha: 01

[Code]
var
  DbModePage: TWizardPage;
  DbServerRadio: TNewRadioButton;
  DbTerminalRadio: TNewRadioButton;
  DbHostLabel: TNewStaticText;
  DbHostEdit: TNewEdit;
  SelectedDbHost: String;
  IsRecoveryInstall: Boolean;

function IsExistingUnitecInstall(): Boolean;
begin
  { Sinais duraveis de instalacao anterior — nao usa so index.php (Setup sempre copia). }
  Result :=
    FileExists(ExpandConstant('{#MyAppDir}\.env')) or
    FileExists(ExpandConstant('{#MyAppDir}\.env.backup')) or
    FileExists(ExpandConstant('{#MyAppDir}\.env.production')) or
    FileExists(ExpandConstant('{#MyAppDir}\tools\mysql\data\ibdata1')) or
    DirExists(ExpandConstant('{#MyAppDir}\tools\mysql\data\unitec_erp'));
end;

function InitializeSetup(): Boolean;
var
  FreeMB, TotalMB: Cardinal;
begin
  Result := True;
  SelectedDbHost := '127.0.0.1';
  IsRecoveryInstall := IsExistingUnitecInstall();

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

  if IsRecoveryInstall then
  begin
    MsgBox(
      'Instalacao existente detectada em C:\UNITECNOLOGIA_WEB.' + #13#10 + #13#10 +
      'O instalador vai atualizar os arquivos do programa.' + #13#10 + #13#10 +
      'Pacote padrao: mantem .env e banco.' + #13#10 +
      'Pacote com dados de desenvolvimento embutidos: substitui .env e banco.',
      mbInformation, MB_OK);
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
  DbServerRadio.Caption := 'Servidor do banco (este PC) - MariaDB na rede local, porta 3306';
  DbServerRadio.Checked := True;
  DbServerRadio.OnClick := @DbModeChanged;

  DbTerminalRadio := TNewRadioButton.Create(DbModePage);
  DbTerminalRadio.Parent := DbModePage.Surface;
  DbTerminalRadio.Left := 0;
  DbTerminalRadio.Top := 28;
  DbTerminalRadio.Width := DbModePage.SurfaceWidth;
  DbTerminalRadio.Caption := 'Terminal (banco em outro PC da loja)';
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
  HasDevSeed: Boolean;
begin
  if CurStep = ssPostInstall then
  begin
    HasDevSeed := FileExists(ExpandConstant('{app}\installer\seed\INCLUDE_DEV_DATA.flag'));

    Params := '-Sta -WindowStyle Hidden -NoProfile -ExecutionPolicy Bypass -File "' +
      ExpandConstant('{app}\scripts\instalar-tudo.ps1') +
      '" -NoPause -FromSetup -AppPath "' + ExpandConstant('{app}') +
      '" -AppUrl "{#SiteBase}" -DbHost "' + SelectedDbHost + '"';

    if HasDevSeed then
      Params := Params + ' -ApplyBundledSeed'
    else if IsRecoveryInstall then
      Params := Params + ' -Recovery';

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
