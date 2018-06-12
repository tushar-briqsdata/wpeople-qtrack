<?php

/**
 * Created by PhpStorm.
 * User: jorgesalcedo
 * Date: 8/30/17
 * Time: 6:28 PM
 */

namespace Mautic\AwsswfBundle\Task;

use \Aws\Credentials\CredentialProvider;
use \Aws\Swf\SwfClient;

class ActivityTask {

    protected $activity;
    protected $domain;
    protected $swfclient;

    public function __construct($domain, $activity) {
        //TODO: Validate activity
        $this->activity = $activity;
        $this->domain = $domain;
        $credentials = CredentialProvider::defaultProvider();
        $this->swfclient = SwfClient::factory([
                    "region" => "us-east-1",
                    "version" => "2012-01-25",
                    "credentials" => $credentials
        ]);
    }

    public function getEventName() {
        return implode(".", [
            $this->domain,
            "activity",
            $this->getActivityId()
        ]);
    }

    public function getEventType() {
        return $this->getActivity()["activityType"];
    }

    public function getActivityId() {
        return $this->activity["activityId"];
    }

    public function getActivity() {
        return $this->activity;
    }
    
    public function getActivityToken() {
        return $this->activity['taskToken'];
    }
    
    public function getActivityInput() {
        return $this->activity['input'];
    }
    
    /**
     * Finish activity task
     * @param type $taskResult
     */
    public function finishActivityTask($taskResult) {
        try {
            $this->swfclient->respondActivityTaskCompleted([
                'result' => isset($taskResult['result']) ? $taskResult['result'] : '',
                'taskToken' => $this->getActivityToken()
            ]);
        } catch (\Exception $e) {
            echo 'Error while finish activity - '.$e->getMessage();
        }
    }
    
    /**
     * Manually fail activity task
     * @param type $reason
     */
    public function failActivityTask($reason) {
        try {
            $this->swfclient->respondActivityTaskFailed([
                'reason' => isset($reason['reason']) ? $reason['reason'] : '',
                'taskToken' => $this->getActivityToken()
            ]);
        } catch (\Exception $e) {
            echo 'Error while failing activity - '.$e->getMessage();
        }
    }
    
    /**
     * Record activity heartbeat
     */
    public function recordActivityTaskHeartbeat() {
        try {
            $this->swfclient->recordActivityTaskHeartbeat([
                'taskToken' => $this->getActivityToken()
            ]);
        } catch (\Exception $e) {
            echo 'Error while reporting healthbeat - '.$e->getMessage();
        }
    }
}
