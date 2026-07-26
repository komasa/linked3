/**
 * Linked3 Eco Rewrite JS
 * Extracted from: admin/views/dashboard/partials/eco-rewrite.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-rewrite.js
 */

(function(){
    var input = document.getElementById('rewrite-input');
    var output = document.getElementById('rewrite-output');
    var modeSel = document.getElementById('rewrite-mode');
    var intensitySel = document.getElementById('rewrite-intensity');
    var descEl = document.getElementById('rewrite-mode-desc');
    var btn = document.getElementById('rewrite-run');
    var copyBtn = document.getElementById('rewrite-copy');
    var swapBtn = document.getElementById('rewrite-swap');

    // 模式说明
    var modeDescs = {
        'renzhenfei': '🧬 任正非化: 以危机意识开篇, 用灰度思维处理矛盾, 善用军事隐喻, 强调熵减和主航道, 语言朴实有力, 结尾回到客户价值。注入任正非的思想DNA: 活下去是最高纲领。',
        'liuxiaopai': '🧬 刘小排化: 从用户具体痛点开始, 用第一人称经历, 发现反共识, 极简表达, 具体数字, 结尾分享思考过程, 偶尔自嘲。注入刘小排的产品创业者DNA。',
        'leijun': '🧬 雷军化: 从用户真实需求出发, 工程师思维拆解, 用极致一词, 口语化表达, 具体价格参数对比, 强调参与感, 结尾回到让每个人享受科技。',
        'zhangyiming': '🧬 张一鸣化: 延迟满足思维, 算法和系统隐喻, Context not Control, 第一性原理, 极度理性, 假设-验证-迭代框架, 关注人才密度, Always Day 1。',
        'luoxiang': '🧬 罗翔化: 从极端案例切入, 法益和权利框架, 圆圈正义隐喻, 自嘲式幽默, 电车难题式思想实验, 结尾回到做谦卑的人, 语言有温度。',
        'wujinglian': '🧬 吴敬琏化: 从制度层面分析, 经济学术语但会解释, 历史对比, 严谨但不晦涩, 强调市场化改革, 忧患意识, 数据支撑, 结尾回到长期挑战。',
        'humanize_g1': '👤 G1初代脱壳: FP部剥骨(提取语义核)→EX部破壁(消灭连接词)→C部绞杀(抹杀AI特征)→A部缝合(组装人类文本)。第一代脱壳, 去除明显AI痕迹。',
        'humanize_g2': '👤 G2重组变异: 在G1基础上, EX部交叉突变(倒装/断句/意象并置), C部二次绞杀, O部降维查翻译腔与中立感。第二代变异, 注入非线性结构。',
        'humanize_g3': '👤 G3终极坍缩: C部终选(0%AI特征), O部零盲区语感确认, A部字数对齐+瑕疵植入。终极目标: 0%AI特征+100%人类混沌感。',
        'humanize_emotion': '👤 情绪注入: 消除机械中立, 注入极性情绪和微观偏见。让文本有立场、有温度、有偏好。',
        'humanize_oral': '👤 口语盐化: 注入口语表达、自嘲、微观偏见。让文本像在和朋友聊天, 而非机器播报。',
        'humanize_flaw': '👤 瑕疵植入: 故意漏冠词、介词, 制造不完美表达。人类写作天然有瑕疵, 完美反而是AI特征。',
        'rewrite_fidelity': '✏️ 语义保真改写: 保持原文语义不变, 仅改变表达方式。适用于降重和去AI味。',
        'rewrite_synonym': '✏️ 同义重构: 用同义词和近义词替换, 保持语义但改变用词。适用于内容去重。',
        'rewrite_perspective': '✏️ 视角转换: 从不同视角重新叙述同一内容。如从第一人称转第三人称, 或从用户视角转产品视角。',
        'rewrite_dedup': '✏️ 降重去重: 大幅改写以降低与原文的相似度, 适用于学术降重和内容原创化。',
        'polish_grammar': '💎 语法修正: 修正语法错误、标点错误、用词不当。保持原文结构和语义。',
        'polish_vocabulary': '💎 用词升级: 将口语化用词升级为更专业的表达, 或将晦涩用词简化为更易懂的表达。',
        'polish_rhythm': '💎 节奏优化: 调整句子长短、段落节奏, 让文章读起来更有韵律感。',
        'polish_logic': '💎 逻辑强化: 加强论点之间的逻辑连接, 补充缺失的推理步骤。',
        'expand_detail': '📈 细节填充: 在关键论点处补充具体细节、数据、案例, 增加信息密度。',
        'expand_argument': '📈 论证展开: 将简略的论点展开为完整的论证链条, 包含前提、推理、结论。',
        'expand_scene': '📈 场景描写: 在叙事处增加场景描写, 增强画面感和沉浸感。',
        'expand_case': '📈 案例补充: 为抽象观点补充具体案例, 增强说服力。',
        'shorten_core': '📉 核心提取: 提取文章的核心观点, 删除所有修饰和铺垫。',
        'shorten_redundancy': '📉 冗余删除: 删除重复表达、无效修饰、空话套话。',
        'shorten_tldr': '📉 TL;DR: 生成一段话的摘要, 让读者快速了解全文要点。',
        'shorten_bullets': '📉 要点提炼: 将文章提炼为要点列表, 每个要点一句话。'
    };

    modeSel.addEventListener('change', function(){
        var desc = modeDescs[this.value] || '选择处理模式后, 这里会显示该模式的详细说明。';
        descEl.innerHTML = desc;
    });

    input.addEventListener('input', function(){
        document.getElementById('rewrite-input-count').textContent = this.value.length;
    });

    btn.addEventListener('click', function(){
        var text = input.value.trim();
        if (!text) { alert('请输入原文'); return; }
        var mode = modeSel.value;
        var intensity = intensitySel.value;
        // v17.2: 读取人类化模块
        var humanizeModules = [];
        document.querySelectorAll('.rw-humanize-module:checked').forEach(function(cb) {
            humanizeModules.push(cb.value);
        });

        btn.disabled = true;
        btn.textContent = '处理中...';

        // 模拟处理 (实际应调用AJAX)
        setTimeout(function(){
            var result = simulateProcess(text, mode, intensity);
            // v17.2: 叠加人类化模块
            if (humanizeModules.length > 0) {
                result = applyHumanizeModules(result, humanizeModules);
            }
            output.value = result;
            document.getElementById('rewrite-output-count').textContent = result.length;
            var change = Math.round(Math.abs(result.length - text.length) / Math.max(text.length, 1) * 100);
            document.getElementById('rewrite-change').textContent = change;
            btn.disabled = false;
            btn.textContent = '✏️ 执行';
        }, 1500);
    });

    // v17.2: 人类化模块叠加处理
    function applyHumanizeModules(text, modules) {
        var result = text;
        if (modules.indexOf('g1') >= 0 || modules.indexOf('g2') >= 0 || modules.indexOf('g3') >= 0) {
            // G1: 剥骨+破壁+绞杀
            result = result.replace(/总之[，,]?/g, '').replace(/综上所述[，,]?/g, '')
                .replace(/首先[，,]/g, '').replace(/其次[，,]/g, '').replace(/最后[，,]/g, '')
                .replace(/值得注意的是[，,]?/g, '').replace(/需要指出的是[，,]?/g, '');
        }
        if (modules.indexOf('g2') >= 0 || modules.indexOf('g3') >= 0) {
            // G2: 倒装+断句+意象并置
            result = result.replace(/([。])/g, '$1\n');
            var sentences = result.split('\n').filter(function(s){ return s.trim().length > 0; });
            if (sentences.length > 3) {
                var tmp = sentences[1]; sentences[1] = sentences[2]; sentences[2] = tmp;
                result = sentences.join('\n');
            }
        }
        if (modules.indexOf('emotion') >= 0 || modules.indexOf('g3') >= 0) {
            result = result.replace(/(.{30,50})(。)/, '$1！\n\n说实话, 这让我挺意外的。');
        }
        if (modules.indexOf('oral') >= 0 || modules.indexOf('g3') >= 0) {
            var oralInserts = ['讲真, ', '你想想, ', '怎么说呢, ', '老实说, '];
            result = oralInserts[Math.floor(Math.random() * oralInserts.length)] + result;
        }
        if (modules.indexOf('flaw') >= 0 || modules.indexOf('g3') >= 0) {
            result = result.replace(/一个/g, '个').replace(/这个/g, '这');
        }
        return result;
    }

    function simulateProcess(text, mode, intensity) {
        var result = text;

        // XX化模式 (人物化)
        var personStyles = {
            'renzhenfei': function(t) {
                return '活下去, 是最高纲领。\n\n' + t.replace(/重要/g, '生死攸关').replace(/发展/g, '主航道').replace(/问题/g, '矛盾')
                    .replace(/总之/g, '说到底').replace(/应该/g, '必须')
                    + '\n\n胜则举杯相庆, 败则拼死相救。凡是不能解释为客户价值的动作, 都需要被重新审视。';
            },
            'liuxiaopai': function(t) {
                return '说实话, 我踩过这个坑。\n\n' + t.replace(/非常重要/g, '其实没那么复杂').replace(/需要/g, '我试过')
                    .replace(/总之/g, '后来发现') + '\n\n如果我是用户, 我会怎么想?';
            },
            'leijun': function(t) {
                return '和大家交个底。\n\n' + t.replace(/高端/g, '极致').replace(/优秀/g, '感动人心')
                    .replace(/价格/g, '价格厚道') + '\n\n让每个人都能享受科技的乐趣。';
            },
            'zhangyiming': function(t) {
                return t.replace(/感觉/g, '判断').replace(/应该/g, '假设')
                    .replace(/重要/g, '关键').replace(/快速/g, '高效')
                    + '\n\nContext, not Control. Always Day 1.';
            },
            'luoxiang': function(t) {
                return '如果张三遇到了这个问题...\n\n' + t.replace(/应该/g, '是否有权').replace(/正确/g, '正义')
                    + '\n\n这样做真的正义吗? 做一个谦卑的人。';
            },
            'wujinglian': function(t) {
                return '从制度层面来看, ' + t.replace(/发展/g, '市场化改革').replace(/问题/g, '制度性障碍')
                    .replace(/解决/g, '改革') + '\n\n如果不改革, 后果是深远的。';
            }
        };

        if (personStyles[mode]) {
            result = personStyles[mode](text);
        } else if (mode.startsWith('humanize')) {
            result = text
                .replace(/总之/g, '说到底').replace(/综上所述/g, '归根结底')
                .replace(/值得注意的是/g, '有意思的是').replace(/需要指出的是/g, '讲真')
                .replace(/首先/g, '先说').replace(/其次/g, '再就是').replace(/最后/g, '对了')
                .replace(/然而/g, '不过').replace(/因此/g, '所以嘛')
                .replace(/此外/g, '还有').replace(/同时/g, '顺便说');

            if (mode === 'humanize_g2' || mode === 'humanize_g3') {
                var sentences = result.split(/([。！？])/);
                var shuffled = [];
                for (var i = 0; i < sentences.length; i += 2) {
                    if (sentences[i]) {
                        shuffled.push(sentences[i] + (sentences[i+1] || ''));
                    }
                }
                if (shuffled.length > 3) {
                    var mid = Math.floor(shuffled.length / 2);
                    var temp = shuffled[0];
                    shuffled[0] = shuffled[mid];
                    shuffled[mid] = temp;
                }
                result = shuffled.join('\n');
            }

            if (mode === 'humanize_emotion' || mode === 'humanize_g3') {
                result = result.replace(/(.{30,50})(。)/, '$1！\n\n说实话, 这让我挺意外的。');
            }

            if (mode === 'humanize_oral' || mode === 'humanize_g3') {
                var oralInserts = ['讲真, ', '你想想, ', '怎么说呢, ', '老实说, '];
                var insertIdx = Math.floor(Math.random() * oralInserts.length);
                result = oralInserts[insertIdx] + result;
            }

            if (mode === 'humanize_flaw' || mode === 'humanize_g3') {
                result = result.replace(/一个/g, '个').replace(/这个/g, '这');
            }
        } else {
            // 其他模式: 改写/润色/扩写/缩写
            if (mode.startsWith('rewrite')) {
                result = text.replace(/重要/g, '关键').replace(/方法/g, '策略').replace(/问题/g, '挑战');
            } else if (mode.startsWith('polish')) {
                result = text.replace(/好的/g, '优秀的').replace(/很多/g, '众多').replace(/说/g, '阐述');
            } else if (mode.startsWith('expand')) {
                result = text.replace(/([。])/g, '$1\n\n具体来说, ');
            } else if (mode.startsWith('shorten')) {
                var sentences = text.split(/[。！？\n]/).filter(function(s){ return s.trim().length > 10; });
                if (mode === 'shorten_tldr') {
                    result = 'TL;DR: ' + sentences.slice(0, 2).join('。') + '。';
                } else if (mode === 'shorten_bullets') {
                    result = sentences.slice(0, 5).map(function(s, i){ return '• ' + s.trim(); }).join('\n');
                } else {
                    result = sentences.slice(0, Math.ceil(sentences.length * 0.6)).join('。') + '。';
                }
            }
        }

        if (intensity === 'light') {
            result = text.replace(/总之/g, '说到底').replace(/综上所述/g, '归根结底');
        }

        return result;
    }

    copyBtn.addEventListener('click', function(){
        if (!output.value) { alert('暂无结果可复制'); return; }
        navigator.clipboard.writeText(output.value).then(function(){ alert('已复制到剪贴板'); });
    });

    swapBtn.addEventListener('click', function(){
        if (!output.value) { alert('暂无结果可替换'); return; }
        input.value = output.value;
        output.value = '';
        document.getElementById('rewrite-input-count').textContent = input.value.length;
        document.getElementById('rewrite-output-count').textContent = 0;
    });
})();
