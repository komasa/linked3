# CI Baseline Report

**Generated**: 2026-07-20 00:10
**Files scanned**: 533
**Total violations**: 138

## Violation Summary

| Rule | Count | Severity |
|------|-------|----------|
| security_nonce_missing | 62 | critical |
| dead_code_deprecated | 40 | info |
| complexity_high | 36 | medium |
| **TOTAL** | **138** | |

## Type Coverage

| Metric | Value |
|--------|-------|
| Functions | 3178 |
| Parameters total | 2873 |
| Parameters typed | 1873 (65.2%) |
| Return typed | 2702 (85.0%) |

## Top Files by Violation Count

### src/Classes/Dashboard/Ajax/Actions/DashboardAIConfigActions.php (24 violations)

- **L39** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L52** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L65** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L79** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L93** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- ... and 19 more

### src/Classes/Dashboard/Ajax/DashboardAjaxGenesis.php (15 violations)

- **L56** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_generate' without nonce check
- **L57** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_styles' without nonce check
- **L58** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_generate_multi' without nonce check
- **L59** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_test_connection' without nonce check
- **L62** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_start_job' without nonce check
- ... and 10 more

### src/Classes/Dashboard/Ajax/Actions/DashboardGenesisActions.php (12 violations)

- **L30** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_generate' without nonce check
- **L31** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_styles' without nonce check
- **L32** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_generate_multi' without nonce check
- **L33** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_test_connection' without nonce check
- **L36** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_start_job' without nonce check
- ... and 7 more

### src/Classes/Dashboard/Ajax/Actions/DashboardDiagramActions.php (8 violations)

- **L31** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L44** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L57** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L71** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L20** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_diagram_generate' without nonce check
- ... and 3 more

### src/Classes/Dashboard/DashboardAjaxRegistrarLegacy.php (7 violations)

- **L115** [dead_code_deprecated] Deprecated marker: * @deprecated G2.1 Migrated to DashboardTemplateActions::template_add()
- **L120** [dead_code_deprecated] Deprecated marker: * @deprecated G2.1 Migrated to DashboardTemplateActions::template_update()
- **L125** [dead_code_deprecated] Deprecated marker: * @deprecated G2.1 Migrated to DashboardTemplateActions::template_delete()
- **L130** [dead_code_deprecated] Deprecated marker: * @deprecated G2.1 Migrated to DashboardTemplateActions::template_get()
- **L143** [dead_code_deprecated] Deprecated marker: * @deprecated G2.1 Migrated to DashboardKeywordActions::keyword_fetch_hot()
- ... and 2 more

### src/Classes/Dashboard/Ajax/Actions/DashboardContentActions.php (6 violations)

- **L30** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L43** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L56** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L20** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_generate_outline' without nonce check
- **L21** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_generate_section' without nonce check
- ... and 1 more

### src/Classes/Dashboard/Ajax/Actions/DashboardVideoActions.php (6 violations)

- **L30** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L43** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L56** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L20** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_video_generate_script' without nonce check
- **L21** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_video_outline' without nonce check
- ... and 1 more

### src/Classes/Dashboard/Ajax/Actions/DashboardChartActions.php (4 violations)

- **L29** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L42** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 This delegate exists for backward compatibility.
- **L20** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_chart_outline' without nonce check
- **L21** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_chart_segment' without nonce check

### src/Classes/Dashboard/Ajax/Actions/DashboardKeywordActions.php (4 violations)

- **L186** [dead_code_deprecated] Deprecated marker: * @deprecated G2.1 Still delegates to legacy; will be migrated in G2.2.
- **L194** [dead_code_deprecated] Deprecated marker: * @deprecated G2.1 Still delegates to legacy; will be migrated in G2.2.
- **L203** [dead_code_deprecated] Deprecated marker: * @deprecated G2.1 Still delegates to legacy; will be migrated in G2.2.
- **L212** [dead_code_deprecated] Deprecated marker: * @deprecated G2.1 Still delegates to legacy; will be migrated in G2.2.

### src/Classes/Chat/ChatHooksRegistrar.php (3 violations)

- **L20** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_chat_send' without nonce check
- **L21** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_chat_send' without nonce check
- **L22** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_chat_history' without nonce check

### src/Classes/Dashboard/Ajax/Actions/DashboardGenesisV9Actions.php (3 violations)

- **L28** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_generate_v9' without nonce check
- **L29** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_v9_stage1' without nonce check
- **L30** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_v9_stage2' without nonce check

### src/Classes/Dashboard/GenesisV9Stages.php (2 violations)

- **L12** [complexity_high] Method 'ajax_genesis_v9_stage1' complexity=53, lines=255
- **L268** [complexity_high] Method 'ajax_genesis_v9_stage2' complexity=33, lines=219

### src/Classes/Genesis/GenesisPatchStage3.php (2 violations)

- **L8** [complexity_high] Method 'ajax_seed_generate_full' complexity=37, lines=204
- **L213** [complexity_high] Method 'ajax_v9_stage1_fixed' complexity=38, lines=143

### src/Classes/Genesis/GenesisRecommendationScorer.php (2 violations)

- **L12** [complexity_high] Method 'scoreByFeatures' complexity=63, lines=132
- **L145** [complexity_high] Method 'applyModeFilters' complexity=40, lines=149

### src/Classes/Genesis/ScriptPatchV1010.php (2 violations)

- **L23** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_video_generate_v10' without nonce check
- **L24** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_charts_generate_v10' without nonce check

### src/Classes/XHS/XHSAjaxActions.php (2 violations)

- **L27** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_xhs_generate' without nonce check
- **L28** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_xhs_optimize_prompt' without nonce check

### src/Includes/functions-events.php (2 violations)

- **L86** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 Use Linked3\Includes\Linked3_EventBus::dispatch().
- **L102** [dead_code_deprecated] Deprecated marker: * @deprecated 27.1.0 Use Linked3\Includes\Linked3_EventBus::subscribe().

### src/Classes/Admin/PostMetabox.php (1 violations)

- **L49** [complexity_high] Method 'render_metabox' complexity=31, lines=166

### src/Classes/AutoGPT/Cron/AutoGPTCron.php (1 violations)

- **L42** [complexity_high] Method 'run' complexity=26, lines=136

### src/Classes/AutoGPT/Processors/ContentWritingProcessor.php (1 violations)

- **L36** [complexity_high] Method 'process' complexity=35, lines=208

