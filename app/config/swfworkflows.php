<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

return [
    "workflowExecutionRetentionPeriodInDays" => "90",
    'activities' => [
        // BannerProcessWorkFlow Activities
        [
            "domain" => "WSuite",
            "name" => "DatabaseMigration",
            "description" => "Build basic folder like cache and logs",
            "version" => "1",
            'defaultTaskScheduleToCloseTimeout' => '31536000',
            'defaultTaskScheduleToStartTimeout' => '31536000',
            'defaultTaskStartToCloseTimeout' => '31536000',
            'defaultTaskHeartbeatTimeout' => '1500',
            "defaultTaskList" => [
                "name" => "default"
            ]
        ],
        [
            "domain" => "WSuite",
            "name" => "InstallFixtures",
            "description" => "Build basic folder like cache and logs",
            "version" => "1",
            'defaultTaskScheduleToCloseTimeout' => '31536000',
            'defaultTaskScheduleToStartTimeout' => '31536000',
            'defaultTaskStartToCloseTimeout' => '31536000',
            'defaultTaskHeartbeatTimeout' => '1500',
            "defaultTaskList" => [
                "name" => "default"
            ]
        ],
        [
            "domain" => "WSuite",
            "name" => "InsertAdminUser",
            "description" => "Build basic folder like cache and logs",
            "version" => "1",
            'defaultTaskScheduleToCloseTimeout' => '31536000',
            'defaultTaskScheduleToStartTimeout' => '31536000',
            'defaultTaskStartToCloseTimeout' => '31536000',
            'defaultTaskHeartbeatTimeout' => '1500',
            "defaultTaskList" => [
                "name" => "default"
            ]
        ],
        [
            "domain" => "WSuite",
            "name" => "InsertClientAdminUser",
            "description" => "Build basic folder like cache and logs",
            "version" => "1",
            'defaultTaskScheduleToCloseTimeout' => '31536000',
            'defaultTaskScheduleToStartTimeout' => '31536000',
            'defaultTaskStartToCloseTimeout' => '31536000',
            'defaultTaskHeartbeatTimeout' => '1500',
            "defaultTaskList" => [
                "name" => "default"
            ]
        ],
        [
            "domain" => "WSuite",
            "name" => "InstallPlugins",
            "description" => "Build basic folder like cache and logs",
            "version" => "1",
            'defaultTaskScheduleToCloseTimeout' => '31536000',
            'defaultTaskScheduleToStartTimeout' => '31536000',
            'defaultTaskStartToCloseTimeout' => '31536000',
            'defaultTaskHeartbeatTimeout' => '1500',
            "defaultTaskList" => [
                "name" => "default"
            ]
        ]
    ],
    'workflows' => [
        [
            "domain" => "WSuite",
            "name" => "EnablePeopleService",
            "version" => "1",
            "defaultExecutionStartToCloseTimeout" => "31536000",
            "defaultTaskStartToCloseTimout" => "31536000",
            "defaultChildPolicy" => "TERMINATE",
            "defaultTaskList" => [
                "name" => "default"
            ]
        ]
    ],
];
