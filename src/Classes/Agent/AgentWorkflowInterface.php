<?php

declare(strict_types=1);
/**
 * Linked3 Agent Workflow Interface — v5.5.0
 *
 * 独立文件确保接口在所有实现类之前加载 (glob_scan 按字母排序)
 *
 * @package Linked3\Agent
 * @since 5.5.0.2
 */
namespace Linked3\Classes\Agent;

if (!defined('ABSPATH')) exit;

interface AgentWorkflowInterface {
    public function execute(array $input): array;
    public function getName(): string;
    public function getSteps(): array;
}
