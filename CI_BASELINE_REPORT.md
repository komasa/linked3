# CI Baseline Report

**Generated**: 2026-07-20 00:10
**Files scanned**: 453
**Total violations**: 212

## Violation Summary

| Rule | Count | Severity |
|------|-------|----------|
| security_nonce_missing | 62 | critical |
| security_unprepared_sql | 55 | high |
| dead_code_deprecated | 40 | info |
| complexity_high | 36 | medium |
| security_unescaped_echo | 19 | high |
| **TOTAL** | **212** | |

## Type Coverage

| Metric | Value |
|--------|-------|
| Functions | 3185 |
| Parameters total | 2873 |
| Parameters typed | 1873 (65.2%) |
| Return typed | 2711 (85.1%) |

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

### src/Classes/OS/Api/OSWidget.php (12 violations)

- **L58** [security_unescaped_echo] Potential unescaped output: echo $args['before_widget'];
- **L61** [security_unescaped_echo] Potential unescaped output: echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'
- **L77** [security_unescaped_echo] Potential unescaped output: echo $args['after_widget'];
- **L89** [security_unescaped_echo] Potential unescaped output: <label for="<?php echo $this->get_field_id('title'); ?>">标题:</label>
- **L90** [security_unescaped_echo] Potential unescaped output: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
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

### src/Classes/Chat/Shortcode/ChatShortcode.php (4 violations)

- **L76** [security_unescaped_echo] Potential unescaped output: <div class="linked3-chat-window" <?php echo $embedded ? '' : 'style="display:none;"'; ?>>
- **L78** [security_unescaped_echo] Potential unescaped output: <span class="linked3-chat-title"><?php echo $title; ?></span>
- **L82** [security_unescaped_echo] Potential unescaped output: <div class="linked3-chat-msg linked3-chat-bot"><?php echo $greeting; ?></div>
- **L52** [complexity_high] Method 'widget_html' complexity=21, lines=78

### src/Classes/ContentWriter/ContentTemplateManager.php (4 violations)

- **L45** [security_unprepared_sql] SQL operation without prepare(): $wpdb->insert($table, [
- **L100** [security_unprepared_sql] SQL operation without prepare(): $wpdb->insert($table, [
- **L138** [security_unprepared_sql] SQL operation without prepare(): $wpdb->update($table, $update, ['id' => $id, 'user_id' => $user_id], $fmt, ['%d', '%d']);
- **L152** [security_unprepared_sql] SQL operation without prepare(): return (bool) $wpdb->delete($table, ['id' => $id, 'user_id' => $user_id, 'is_starter' => 0], ['%d', 

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

### src/Classes/Publish/PublishTargetRepository.php (4 violations)

- **L140** [security_unprepared_sql] SQL operation without prepare(): $wpdb->update($table, ['is_default' => 0], ['user_id' => $user_id], ['%d'], ['%d']);
- **L185** [security_unprepared_sql] SQL operation without prepare(): $wpdb->update($table, ['is_default' => 0], ['user_id' => $user_id], ['%d'], ['%d']);
- **L192** [security_unprepared_sql] SQL operation without prepare(): $wpdb->update($table, $update, ['id' => $id, 'user_id' => $user_id], $fmt, ['%d', '%d']);
- **L209** [security_unprepared_sql] SQL operation without prepare(): return (bool) $wpdb->update(

### src/Classes/Security/AsyncQueue.php (4 violations)

- **L46** [security_unprepared_sql] SQL operation without prepare(): $wpdb->insert($table, [
- **L89** [security_unprepared_sql] SQL operation without prepare(): $wpdb->update($table, [
- **L110** [security_unprepared_sql] SQL operation without prepare(): $wpdb->update($table, [
- **L125** [security_unprepared_sql] SQL operation without prepare(): $wpdb->update($table, [

### src/Classes/Billing/StripeGateway.php (3 violations)

- **L341** [security_unprepared_sql] SQL operation without prepare(): $wpdb->insert($table, [
- **L438** [security_unprepared_sql] SQL operation without prepare(): $wpdb->insert($table, [
- **L469** [security_unprepared_sql] SQL operation without prepare(): $wpdb->update($table, [

### src/Classes/Chat/ChatHooksRegistrar.php (3 violations)

- **L20** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_chat_send' without nonce check
- **L21** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_chat_send' without nonce check
- **L22** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_chat_history' without nonce check

### src/Classes/Chat/Storage/ChatStorage.php (3 violations)

- **L47** [security_unprepared_sql] SQL operation without prepare(): $wpdb->insert($table, [
- **L83** [security_unprepared_sql] SQL operation without prepare(): $wpdb->update($table, [
- **L116** [security_unprepared_sql] SQL operation without prepare(): return (bool) $wpdb->delete($table, ['session_id' => $session_id, 'user_id' => $user_id], ['%s', '%d

### src/Classes/Core/AIDispatcher.php (3 violations)

- **L472** [security_unprepared_sql] SQL operation without prepare(): $wpdb->insert($table, [
- **L581** [security_unprepared_sql] SQL operation without prepare(): $wpdb->update(
- **L211** [complexity_high] Method 'call_single' complexity=27, lines=237

### src/Classes/Dashboard/Ajax/Actions/DashboardGenesisV9Actions.php (3 violations)

- **L28** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_generate_v9' without nonce check
- **L29** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_v9_stage1' without nonce check
- **L30** [security_nonce_missing] AJAX handler 'wp_ajax_linked3_genesis_v9_stage2' without nonce check

### src/Classes/Dashboard/Ajax/Actions/DashboardQueueActions.php (3 violations)

- **L54** [security_unprepared_sql] SQL operation without prepare(): $items = $wpdb->get_results("SELECT * FROM {$table} {$where} ORDER BY added_at DESC LIMIT 50", ARRAY
- **L86** [security_unprepared_sql] SQL operation without prepare(): $wpdb->update($table, ['status' => 'pending', 'error_message' => ''], ['id' => $id], ['%s', '%s'], [
- **L105** [security_unprepared_sql] SQL operation without prepare(): $wpdb->delete($table, ['id' => $id], ['%d']);

