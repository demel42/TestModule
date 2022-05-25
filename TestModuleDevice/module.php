<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class TestModuleDevice extends IPSModule
{
    use TestModule\StubsCommonLib;
    use TestModuleLocalLib;

    private $ModuleDir;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyInteger('update_interval', 60);

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->RegisterAttributeString('external_update_interval', '');

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('UpdateStatus', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateStatus", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->OverwriteUpdateInterval();
        }
    }

    private function CheckModulePrerequisites()
    {
        $r = [];

        return $r;
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        return $r;
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        if ($this->version2num($oldInfo) < $this->version2num('1.0.2')) {
            @$varID = $this->GetIDForIdent('UpdateTest');
            if (@$varID != false) {
                $r[] = $this->Translate('Delete variable \'UpdateTest\'');
            }
        }

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        if ($this->version2num($oldInfo) < $this->version2num('1.0.2')) {
            $this->UnregisterVariable('UpdateTest');
        }

        return false;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->SetStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        // Maintain Variables
        $vops = 0;

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $this->SetStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    protected function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('TestModul Device');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'suffix'  => 'Seconds',
            'minimum' => 0,
            'caption' => 'Update interval',
        ];

        return $formElements;
    }

    private function TestMessage()
    {
        $text = 'Test-Nachricht' . PHP_EOL;
        $text .= '' . PHP_EOL;
        $text .= 'Zeile 1' . PHP_EOL;
        $text .= 'Zeile 2' . PHP_EOL;
        $this->PopupMessage($text);
    }

    protected function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update status',
            'onClick' => 'IPS_RequestAction($id, "UpdateStatus", "");',
        ];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Test message',
            'onClick' => 'IPS_RequestAction($id, "TestMessage", "");',
        ];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Reset update',
            'onClick' => 'IPS_RequestAction($id, "ResetUpdate", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded ' => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded ' => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ],
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function ResetUpdate()
    {
        $info = ['Version' => '0.9', 'Build' => 0, 'Date' => 1650201234];
        $this->WriteAttributeString('UpdateInfo', json_encode($info));

        $this->MaintainVariable('UpdateTest', 'update test', VARIABLETYPE_INTEGER, '~UnixTimestampDate', 0, true);

        IPS_ApplyChanges($this->InstanceID);
    }

    private function SetUpdateInterval(int $sec = null)
    {
        if (is_null($sec)) {
            $sec = $this->ReadAttributeString('external_update_interval');
            if ($sec == '') {
                $sec = $this->ReadPropertyInteger('update_interval');
            }
        }
        $this->MaintainTimer('UpdateStatus', $sec * 1000);
    }

    public function OverwriteUpdateInterval(int $sec = null)
    {
        if (is_null($sec)) {
            $this->WriteAttributeString('external_update_interval', '');
        } else {
            $this->WriteAttributeString('external_update_interval', $sec);
        }
        $this->SetUpdateInterval($sec);
    }

    private function UpdateStatus()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        /*
        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return;
        }
         */

        $this->SendDebug(__FUNCTION__, $this->PrintTimer('UpdateStatus'), 0);
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', value=' . $value, 0);

        $r = false;
        switch ($ident) {
            case 'UpdateStatus':
                $this->UpdateStatus();
                break;
            case 'ResetUpdate':
                $this->ResetUpdate();
                break;
            case 'TestMessage':
                $this->TestMessage();
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
        }
    }
}
