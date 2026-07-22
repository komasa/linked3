/**
 * SEO Scorecard metabox JS — fetches the score via AJAX and renders it.
 *
 * @package Linked3
 */
(function () {
    var metabox = document.getElementById('linked3-seo-scorecard-metabox');
    if (!metabox) return;
    var postId = metabox.dataset.postId;
    var nonce = metabox.dataset.nonce;
    var btn = metabox.querySelector('.linked3-score-recompute');
    var numEl = metabox.querySelector('.linked3-score-number');
    var gradeEl = metabox.querySelector('.linked3-score-grade');
    var tipsEl = metabox.querySelector('.linked3-score-tips');
    var errEl = metabox.querySelector('.linked3-score-error');

    function render(res) {
        numEl.textContent = String(res.score);
        var color = res.score >= 80 ? '#2a8a2a' : (res.score >= 60 ? '#c98000' : '#c92a2a');
        numEl.style.color = color;
        gradeEl.textContent = (linked3SeoMetaboxL10n && linked3SeoMetaboxL10n.grade) + res.grade;
        tipsEl.innerHTML = '';
        (res.tips || []).forEach(function (t) {
            var li = document.createElement('li');
            li.textContent = t;
            tipsEl.appendChild(li);
        });
    }

    function fetchScore() {
        btn.disabled = true;
        errEl.textContent = '';
        var body = new FormData();
        body.append('action', 'linked3_seo_score');
        body.append('nonce', nonce);
        body.append('post_id', postId);
        fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                if (res.success) {
                    render(res.data);
                } else {
                    errEl.textContent = (res.data && res.data.message) || 'Error';
                }
            })
            .catch(function (e) { btn.disabled = false; errEl.textContent = String(e); });
    }

    btn.addEventListener('click', fetchScore);
})();
var linked3SeoMetaboxL10n = linked3SeoMetaboxL10n || { grade: 'Grade: ' };
