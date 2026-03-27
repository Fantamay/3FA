<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests d'intégration SIEM — vérifie la structure des règles d'alerte
 * et la cohérence de la configuration sans base de données.
 */
class SiemTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $path = __DIR__ . '/../config/siem/alert_rules.json';
        $this->assertFileExists($path, 'Le fichier alert_rules.json doit exister');
        $content = file_get_contents($path);
        $this->config = json_decode($content, true);
        $this->assertNotNull($this->config, 'alert_rules.json doit être du JSON valide');
    }

    public function testSiemConfigHasMetadata(): void
    {
        $this->assertArrayHasKey('siem', $this->config);
        $this->assertArrayHasKey('name', $this->config['siem']);
        $this->assertArrayHasKey('version', $this->config['siem']);
    }

    public function testAlertRulesExist(): void
    {
        $this->assertArrayHasKey('alert_rules', $this->config);
        $this->assertIsArray($this->config['alert_rules']);
        $this->assertNotEmpty($this->config['alert_rules']);
    }

    public function testAlertRulesHaveRequiredFields(): void
    {
        foreach ($this->config['alert_rules'] as $rule) {
            $this->assertArrayHasKey('id', $rule, "Règle sans id");
            $this->assertArrayHasKey('name', $rule, "Règle sans name");
            $this->assertArrayHasKey('severity', $rule, "Règle sans severity");
            $this->assertArrayHasKey('query', $rule, "Règle sans query");
            $this->assertArrayHasKey('threshold', $rule, "Règle sans threshold");
        }
    }

    public function testAlertRuleIdsAreUnique(): void
    {
        $ids = array_column($this->config['alert_rules'], 'id');
        $this->assertSame(count($ids), count(array_unique($ids)), 'Les IDs de règles doivent être uniques');
    }

    public function testSeverityLevelsAreValid(): void
    {
        $validLevels = ['INFO', 'WARNING', 'HIGH', 'CRITICAL'];
        foreach ($this->config['alert_rules'] as $rule) {
            $this->assertContains(
                $rule['severity'],
                $validLevels,
                "Niveau de sévérité invalide : {$rule['severity']}"
            );
        }
    }

    public function testBruteForceRuleExists(): void
    {
        $ids = array_column($this->config['alert_rules'], 'id');
        $this->assertContains('RULE-001', $ids, 'La règle brute force doit exister');
    }

    public function testBruteForceThresholdIsThree(): void
    {
        $bruteForce = array_filter(
            $this->config['alert_rules'],
            fn($r) => $r['id'] === 'RULE-001'
        );
        $rule = array_values($bruteForce)[0];
        $this->assertSame(3, $rule['threshold']);
    }

    public function testDashboardWidgetsExist(): void
    {
        $this->assertArrayHasKey('dashboard_widgets', $this->config);
        $this->assertNotEmpty($this->config['dashboard_widgets']);
    }

    public function testDashboardWidgetsHaveRequiredFields(): void
    {
        foreach ($this->config['dashboard_widgets'] as $widget) {
            $this->assertArrayHasKey('id', $widget);
            $this->assertArrayHasKey('title', $widget);
            $this->assertArrayHasKey('type', $widget);
            $this->assertArrayHasKey('query', $widget);
        }
    }

    public function testAlertRuleQueriesAreNotEmpty(): void
    {
        foreach ($this->config['alert_rules'] as $rule) {
            $this->assertNotEmpty($rule['query'], "La requête de la règle {$rule['id']} est vide");
        }
    }

    public function testCriticalRulesExist(): void
    {
        $criticalRules = array_filter(
            $this->config['alert_rules'],
            fn($r) => $r['severity'] === 'CRITICAL'
        );
        $this->assertNotEmpty($criticalRules, 'Il doit exister au moins une règle CRITICAL');
    }
}
