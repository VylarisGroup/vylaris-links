/* Vylaris Links – Profilseiten: Eingang, Musik, Partikel, Mauszeiger-Funken, Countdown */
(function () {
  'use strict';
  var body = document.body;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var audio = document.getElementById('bgm');
  var toggle = document.getElementById('music-toggle');

  function setMusic(on) {
    if (!audio) return;
    if (on) { var p = audio.play(); if (p && p.catch) p.catch(function () {}); } else { audio.pause(); }
    if (toggle) { toggle.setAttribute('aria-pressed', on ? 'true' : 'false'); toggle.textContent = on ? 'Stopp' : 'Musik'; }
  }
  if (toggle) toggle.addEventListener('click', function () { setMusic(audio.paused); });

  // Eingang
  var entrance = document.getElementById('entrance');
  if (entrance) {
    document.documentElement.style.overflow = 'hidden';
    var enter = function () {
      entrance.classList.add('open');
      document.documentElement.style.overflow = '';
      setMusic(true);
      setTimeout(function () { entrance.remove(); }, 800);
    };
    entrance.addEventListener('click', enter);
    entrance.focus();
  }

  // Countdown
  var cds = document.querySelectorAll('.countdown[data-target]');
  function tick() {
    cds.forEach(function (el) {
      var t = Date.parse(el.getAttribute('data-target'));
      if (isNaN(t)) return;
      var d = Math.max(0, t - Date.now()), s = Math.floor(d / 1000);
      var vals = { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
      Object.keys(vals).forEach(function (k) { var n = el.querySelector('[data-u="' + k + '"]'); if (n) n.textContent = vals[k]; });
      if (d === 0) { var ttl = el.querySelector('.cd-title'); if (ttl && !ttl.dataset.done) { ttl.dataset.done = '1'; ttl.textContent += ' – es ist soweit!'; } }
    });
  }
  if (cds.length) { tick(); setInterval(tick, 1000); }

  if (reduce) return;

  // Partikel
  var kind = body.getAttribute('data-particles');
  var cv = document.getElementById('fx');
  if (cv && kind && kind !== 'none') {
    var ctx = cv.getContext('2d'), W = 0, H = 0, dpr = Math.min(window.devicePixelRatio || 1, 2), list = [];
    var colors = ['#f87171', '#fbbf24', '#34d399', '#60a5fa', '#c084fc', '#f472b6'];
    var resize = function () { W = innerWidth; H = innerHeight; cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0); };
    var make = function (initial) {
      var p = { x: Math.random() * W, y: initial ? Math.random() * H : -20, r: 1 + Math.random() * 3, v: .4 + Math.random() * 1.1, d: Math.random() * Math.PI * 2, c: colors[Math.random() * colors.length | 0], rot: Math.random() * 6, a: .4 + Math.random() * .6 };
      if (kind === 'hearts') { p.y = initial ? Math.random() * H : H + 20; p.r = 8 + Math.random() * 12; }
      if (kind === 'fireflies') { p.y = Math.random() * H; p.r = 1.5 + Math.random() * 2.5; }
      return p;
    };
    resize(); addEventListener('resize', resize);
    var n = kind === 'hearts' ? 26 : kind === 'fireflies' ? 34 : 70;
    for (var i = 0; i < n; i++) list.push(make(true));
    var draw = function () {
      if (document.hidden) { return requestAnimationFrame(draw); }
      ctx.clearRect(0, 0, W, H);
      list.forEach(function (p, i) {
        p.d += .02;
        if (kind === 'snow') { p.y += p.v; p.x += Math.sin(p.d) * .6; ctx.fillStyle = 'rgba(255,255,255,' + p.a + ')'; ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.3); ctx.fill(); if (p.y > H + 10) list[i] = make(false); }
        else if (kind === 'confetti') { p.y += p.v * 1.6; p.x += Math.sin(p.d) * 1.2; p.rot += .08; ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot); ctx.fillStyle = p.c; ctx.fillRect(-p.r, -p.r / 2, p.r * 2.2, p.r); ctx.restore(); if (p.y > H + 10) list[i] = make(false); }
        else if (kind === 'hearts') { p.y -= p.v; p.x += Math.sin(p.d) * .7; ctx.globalAlpha = p.a; ctx.fillStyle = '#f472b6'; ctx.font = p.r + 'px sans-serif'; ctx.fillText('♥', p.x, p.y); ctx.globalAlpha = 1; if (p.y < -30) list[i] = make(false); }
        else if (kind === 'fireflies') { p.x += Math.cos(p.d) * .5; p.y += Math.sin(p.d * 1.3) * .5; var g = .35 + .65 * Math.abs(Math.sin(p.d * 1.5)); ctx.shadowBlur = 12; ctx.shadowColor = '#fde047'; ctx.fillStyle = 'rgba(253,224,71,' + g + ')'; ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.3); ctx.fill(); ctx.shadowBlur = 0; }
      });
      requestAnimationFrame(draw);
    };
    draw();
  }

  // Funken am Mauszeiger
  var sc = document.getElementById('sparks');
  if (sc && body.getAttribute('data-sparks') === '1') {
    var sx = sc.getContext('2d'), sw = 0, sh = 0, bits = [], last = 0;
    var fit = function () { sw = sc.width = innerWidth; sh = sc.height = innerHeight; };
    fit(); addEventListener('resize', fit);
    addEventListener('pointermove', function (e) {
      var now = performance.now(); if (now - last < 22) return; last = now;
      for (var i = 0; i < 2; i++) bits.push({ x: e.clientX, y: e.clientY, vx: (Math.random() - .5) * 2.4, vy: (Math.random() - .5) * 2.4 - .4, l: 1, h: 40 + Math.random() * 280 });
    }, { passive: true });
    (function loop() {
      sx.clearRect(0, 0, sw, sh);
      bits = bits.filter(function (b) { return b.l > 0; });
      bits.forEach(function (b) { b.x += b.vx; b.y += b.vy; b.l -= .03; sx.fillStyle = 'hsla(' + b.h + ',95%,70%,' + b.l + ')'; sx.beginPath(); sx.arc(b.x, b.y, 2.2 * b.l + .4, 0, 6.3); sx.fill(); });
      requestAnimationFrame(loop);
    })();
  }
})();
