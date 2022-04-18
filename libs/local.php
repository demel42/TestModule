<?php

declare(strict_types=1);

trait TestModuleLocalLib
{
    public static $IS_INVALIDPREREQUISITES = IS_EBASE + 1;
    public static $IS_UPDATEUNCOMPLETED = IS_EBASE + 2;
    public static $IS_INVALIDCONFIG = IS_EBASE + 3;
    public static $IS_DEACTIVATED = IS_EBASE + 4;

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function GetFormStatus()
    {
        $formStatus = [];

        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => self::$IS_INVALIDPREREQUISITES, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid preconditions)'];
        $formStatus[] = ['code' => self::$IS_UPDATEUNCOMPLETED, 'icon' => 'error', 'caption' => 'Instance is inactive (update not completed)'];
        $formStatus[] = ['code' => self::$IS_INVALIDCONFIG, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid configuration)'];
        $formStatus[] = ['code' => self::$IS_DEACTIVATED, 'icon' => 'inactive', 'caption' => 'Instance is inactive (deactivated)'];

        return $formStatus;
    }

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    public function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }
    }
}
