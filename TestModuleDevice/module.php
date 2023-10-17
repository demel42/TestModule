<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class TestModuleDevice extends IPSModule
{
    use TestModule\StubsCommonLib;
    use TestModuleLocalLib;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonContruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyInteger('initial_size', 5 * 1024);
        $this->RegisterPropertyInteger('increment_interval', 100);
        $this->RegisterPropertyInteger('step_interval', 60);

        $this->RegisterAttributeString('data_attribute', json_encode([]));

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('StepTimer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "DoStep", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($timestamp, $senderID, $message, $data)
    {
        parent::MessageSink($timestamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $this->SetStepInterval();
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->SetStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        // Maintain Variables
        $vpos = 0;

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetStatus(IS_INACTIVE);
            $this->MaintainTimer('StepTimer', 0);
            return;
        }

        $this->SetStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetStepInterval();
        }
    }

    private function GetFormElements()
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
            'name'    => 'initial_size',
            'suffix'  => 'KB',
            'minimum' => 0,
            'caption' => 'Estimated initial size of Attribute',
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'increment_interval',
            'minimum' => 0,
            'caption' => 'Steps between increment of size',
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'step_interval',
            'suffix'  => 'Seconds',
            'minimum' => 0,
            'caption' => 'Step interval',
        ];

        return $formElements;
    }

    private function GetFormActions()
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
            'caption' => 'Do step',
            'onClick' => 'IPS_RequestAction($id, "DoStep", "");',
        ];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Clear attribute',
            'onClick' => 'IPS_RequestAction($id, "ClearAttribute", "");',
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function SetStepInterval()
    {
        $sec = $this->ReadPropertyInteger('step_interval');
        $this->MaintainTimer('StepTimer', $sec * 1000);
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;

        switch ($ident) {
            case 'DoStep':
                $this->DoStep();
                break;
            case'ClearAttribute':
                $this->WriteAttributeString('data_attribute', json_encode([]));
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
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
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
        }
    }

    private function DoStep()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $data = $this->ReadAttributeString('data_attribute');
        $old_size = strlen($data);
        if ($data == json_encode([])) {
            $initial_size = $this->ReadPropertyInteger('initial_size');
            $e = [
                'tstamp' => time(),
                'data'   => str_pad(date('d.m.y H:i:s') . ' ', 69, '-'),
            ];
            $l = strlen(json_encode($e));
            $n = (int) ($initial_size * 1024 / $l);
            $list = [];
            for ($i = 0; $i < $n; $i++) {
                $list[] = $e;
            }
            $mode = 'initial';
            $step = 0;
        } else {
            $jdata = json_decode($data, true);
            $e = [
                'tstamp' => time(),
                'data'   => str_pad(date('d.m.y H:i:s') . ' ', 69, '-'),
            ];
            $step = $jdata['step'];
            $list = $jdata['list'];
            $increment_interval = $this->ReadPropertyInteger('increment_interval');
            if ($step % $increment_interval) {
                $list[count($list) - 1] = $e;
                $mode = 'update';
            } else {
                $list[] = $e;
                $mode = 'increase';
            }
            $step++;
        }
        $jdata = [
            'step' => $step,
            'list' => $list,
        ];
        $data = json_encode($jdata);
        $new_size = strlen($data);
        $this->WriteAttributeString('data_attribute', $data);
        $this->SendDebug(__FUNCTION__, 'mode=' . $mode . ', step=' . $step . ', size=' . $new_size . ($old_size != $new_size ? ('(old=' . $old_size . ')') : ''), 0);
        $this->SendDebug(__FUNCTION__, $this->PrintTimer('StepTimer'), 0);
    }
}
