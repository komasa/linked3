/**
 * linked3-tab-overview.js
 * Extracted from: admin/views/dashboard/partials/tab-overview.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-overview.js
 */


                (function(){
                    var overlay = document.getElementById('lk3-wizard-overlay');
                    var stepIdx = 0;
                    var curSteps = [], curUrls = [];

                    function openWizard(icon, name, desc, stepsJson, urlsJson) {
                        stepIdx = 0;
                        curSteps = JSON.parse(stepsJson);
                        curUrls = JSON.parse(urlsJson);
                        document.getElementById('lk3-wizard-icon').textContent = icon;
                        document.getElementById('lk3-wizard-title').textContent = name;
                        document.getElementById('lk3-wizard-desc').textContent = desc;
                        overlay.style.display = 'flex';
                        renderStep();
                    }
                    function renderStep() {
                        document.getElementById('lk3-wizard-stepnum').textContent = '步骤 ' + (stepIdx+1) + '/' + curSteps.length;
                        document.getElementById('lk3-wizard-body').innerHTML =
                            '<div style="background:#F4F4F5;border:1px solid #bbf7d0;padding:12px;border-radius:6px;margin-bottom:12px;">'
                            + '<strong>✅ 当前步骤:</strong> ' + curSteps[stepIdx] + '</div>'
                            + '<p style="font-size:12px;color:#71717A;">点击"前往完成"跳转到对应配置页，完成后返回此向导继续下一步。</p>';
                        // 进度条
                        document.querySelectorAll('.lk3-wizard-dot').forEach(function(d, i){
                            d.style.background = i <= stepIdx ? '#2563eb' : '#e5e7eb';
                        });
                        // 按钮
                        document.getElementById('lk3-wizard-prev').disabled = (stepIdx === 0);
                        var nextBtn = document.getElementById('lk3-wizard-next');
                        if (stepIdx === curSteps.length - 1) {
                            nextBtn.textContent = '🎉 完成';
                        } else if (curUrls[stepIdx]) {
                            nextBtn.textContent = '前往完成 →';
                        } else {
                            // v16.0.24: 无url的说明步骤, 显示"下一步"
                            nextBtn.textContent = '下一步 →';
                        }
                        document.getElementById('lk3-wizard-hint').textContent = curUrls[stepIdx] ? '将跳转: ' + curUrls[stepIdx].split('tab=')[1] : '本步骤为说明性步骤, 点击下一步继续';
                    }
                    document.querySelectorAll('.lk3-wizard-btn').forEach(function(btn){
                        btn.addEventListener('click', function(){
                            openWizard(this.dataset.icon, this.dataset.preset, this.dataset.desc, this.dataset.steps, this.dataset.urls);
                        });
                    });
                    document.getElementById('lk3-wizard-close').addEventListener('click', function(){ overlay.style.display = 'none'; });
                    document.getElementById('lk3-wizard-prev').addEventListener('click', function(){ if (stepIdx > 0) { stepIdx--; renderStep(); } });
                    document.getElementById('lk3-wizard-next').addEventListener('click', function(){
                        if (stepIdx === curSteps.length - 1) {
                            overlay.style.display = 'none';
                            alert('🎉 工作流配置完成! 你可以开始使用了。');
                        } else {
                            // v16.0.24修复: 无url的步骤(如纯说明步骤)不跳转, 直接前进
                            var targetUrl = curUrls[stepIdx];
                            if (targetUrl) {
                                window.open(targetUrl, '_blank');
                            }
                            stepIdx++;
                            renderStep();
                        }
                    });
                    // v27.9.0 (P1-B): 修复鼠标拖选文本时浮层误关 — 追踪 mousedown 起点
                    var wizardMouseDownTarget = null;
                    overlay.addEventListener('mousedown', function(e){
                        wizardMouseDownTarget = e.target;
                    });
                    overlay.addEventListener('click', function(e){
                        // 只有 mousedown 和 mouseup 都在 overlay 背景上才关闭
                        if (e.target === overlay && wizardMouseDownTarget === overlay) {
                            overlay.style.display = 'none';
                        }
                        wizardMouseDownTarget = null;
                    });
                })();
                