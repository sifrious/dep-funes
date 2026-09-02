<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

enum EntityKind: string
{
    case Project = 'project';
    case Site = 'site';
    case Identity = 'identity';
    case Repository = 'repository';
    case Organization = 'organization';
    case Domain = 'domain';
    case UserInput = 'user-input';
    case Conversation = 'conversation';
    case Twinkle = 'twinkle';
    case Plan = 'plan';
    case PlanStep = 'plan-step';
    case WorkKit = 'work-kit';
    case ExecutionRequest = 'execution-request';
    case Run = 'run';
    case RunResult = 'run-result';
    case Commit = 'commit';
}
