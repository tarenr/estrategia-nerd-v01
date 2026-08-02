(function () {
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const animeApi = window.anime || window.animejs || window.AnimeJS || null;
  const animate = animeApi && typeof animeApi.animate === 'function' ? animeApi.animate : null;
  const heroItems = Array.from(document.querySelectorAll('[data-hero-animate]'));

  if (heroItems.length > 0 && !reduceMotion && animate) {
    const prepare = (item, y = 44, scale = 0.96, blur = 12) => {
      if (!item) return;
      item.style.opacity = '0';
      item.style.transform = `translateY(${y}px) scale(${scale})`;
      item.style.willChange = 'opacity, transform, filter';
      item.style.filter = `blur(${blur}px)`;
    };

    const reveal = (target, delay, options = {}) => {
      const nodes = Array.from(document.querySelectorAll(target));
      nodes.forEach((node, index) => {
        window.setTimeout(() => {
          animate(node, {
            opacity: 1,
            y: 0,
            scale: 1,
            filter: 'blur(0px)',
            duration: options.duration || 1050,
            ease: options.ease || 'outExpo',
            onComplete: () => {
              node.style.willChange = '';
            },
          });
        }, delay + (options.stagger ? index * options.stagger : 0));
      });
    };

    prepare(document.querySelector('[data-hero-animate="eyebrow"]'), 28, 0.94, 10);
    prepare(document.querySelector('[data-hero-animate="title-main"]'), 78, 0.92, 16);
    prepare(document.querySelector('[data-hero-animate="title-accent"]'), 58, 0.94, 16);
    prepare(document.querySelector('[data-hero-animate="descriptor"]'), 42, 0.97, 12);
    prepare(document.querySelector('[data-hero-animate="copy"]'), 38, 0.98, 10);
    document.querySelectorAll('[data-hero-animate="support"]').forEach((item) => prepare(item, 30, 0.9, 9));
    prepare(document.querySelector('[data-hero-animate="actions"]'), 34, 0.96, 10);
    prepare(document.querySelector('[data-hero-animate="recent-link"]'), 20, 0.98, 8);

    reveal('[data-hero-animate="eyebrow"]', 180);
    reveal('[data-hero-animate="title-main"]', 360, { duration: 1250 });
    reveal('[data-hero-animate="title-accent"]', 560, { duration: 1180 });
    reveal('[data-hero-animate="descriptor"]', 760);
    reveal('[data-hero-animate="copy"]', 940);
    reveal('[data-hero-animate="support"]', 1120, { duration: 820, stagger: 130 });
    reveal('[data-hero-animate="actions"]', 1380, { duration: 900 });
    reveal('[data-hero-animate="recent-link"]', 1580, { duration: 780 });

    document.querySelectorAll('[data-hero-button]').forEach((button) => {
      button.addEventListener('mouseenter', () => {
        animate(button, { y: -5, scale: 1.035, duration: 280, ease: 'outCubic' });
      });
      button.addEventListener('mouseleave', () => {
        animate(button, { y: 0, scale: 1, duration: 360, ease: 'outCubic' });
      });
    });
  } else {
    heroItems.forEach((item) => {
      item.style.opacity = '';
      item.style.transform = '';
      item.style.filter = '';
    });
  }
}());

(function () {
  const canvas = document.getElementById('siteHeroPreviewCanvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const palette = {
    bg: '#020617',
    board: 'rgba(8, 47, 73, 0.2)',
    boardDeep: 'rgba(2, 18, 31, 0.62)',
    trace: 'rgba(103, 232, 249, 0.16)',
    traceStrong: 'rgba(34, 211, 238, 0.32)',
    traceDraw: 'rgba(34, 211, 238, 0.52)',
    pulse: 'rgba(34, 211, 238, 0.98)',
    pulseGreen: 'rgba(45, 212, 191, 0.94)',
    pulseBlue: 'rgba(59, 130, 246, 0.96)',
    pulsePink: 'rgba(244, 114, 182, 0.95)',
    pulseAmber: 'rgba(251, 191, 36, 0.92)',
    silk: 'rgba(226, 232, 240, 0.22)',
    copper: 'rgba(251, 146, 60, 0.18)',
    microTrace: 'rgba(125, 211, 252, 0.1)',
  };
  const energyColors = [
    palette.pulse,
    palette.pulseGreen,
    palette.pulseBlue,
    palette.pulsePink,
    palette.pulseAmber,
  ];

  let width = 0;
  let height = 0;
  let dpr = 1;
  let scale = 1;
  let ox = 0;
  let oy = 0;
  let frame = 0;
  let traces = [];
  let vias = [];
  let parts = [];
  let explosions = [];
  let portals = [];
  let microTraces = [];
  let microParts = [];

  const project = { w: 1440, h: 820 };
  const x = (value) => ox + value * scale;
  const y = (value) => oy + value * scale;
  const s = (value) => value * scale;

  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = Math.max(1, canvas.offsetWidth);
    height = Math.max(1, canvas.offsetHeight);
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    scale = Math.min(width / project.w, height / project.h) * (width < 768 ? 1.34 : 1.05);
    ox = (width - project.w * scale) / 2;
    oy = (height - project.h * scale) / 2 + (width < 768 ? height * 0.02 : 0);
    buildBoard();
    draw();
  }

  function buildBoard() {
    frame = 0;
    traces = [];
    vias = [];
    explosions = [];
    portals = [];
    microTraces = [];
    microParts = [];
    parts = [
      { type: 'atx', x: 88, y: 86, w: 48, h: 260, label: 'ATX' },
      { type: 'eps', x: 235, y: 78, w: 104, h: 50, label: 'EPS' },
      { type: 'vrm', x: 255, y: 152, w: 300, h: 52, label: 'VRM' },
      { type: 'cpu', x: 430, y: 230, w: 260, h: 236, label: 'CPU' },
      { type: 'ram', x: 770, y: 118, w: 194, h: 380, label: 'DDR5' },
      { type: 'chipset', x: 698, y: 540, w: 166, h: 106, label: 'CHIPSET' },
      { type: 'pcie', x: 394, y: 666, w: 488, h: 28, label: 'PCIe X16' },
      { type: 'pcie', x: 444, y: 720, w: 286, h: 22, label: 'PCIe X1' },
      { type: 'm2', x: 925, y: 552, w: 280, h: 26, label: 'M.2' },
      { type: 'sata', x: 1220, y: 520, w: 56, h: 210, label: 'SATA' },
      { type: 'io', x: 1304, y: 90, w: 74, h: 610, label: 'I/O' },
      { type: 'bios', x: 1032, y: 412, w: 98, h: 54, label: 'BIOS' },
    ];

    const add = (points, options) => {
      traces.push({
        points,
        length: pathLength(points),
        draw: reduceMotion ? 1 : 0,
        drawSpeed: options.drawSpeed || 0.012,
        delay: options.delay || 0,
        pulse: {
          mode: options.mode || 'pingpong',
          t: options.t || 0,
          dir: options.dir || 1,
          speed: options.speed || 0.0028,
          wait: 0,
          waitMax: options.waitMax || 40,
          color: options.color || energyColors[traces.length % energyColors.length],
          collisionCooldown: 0,
          crashed: false,
          hidden: 0,
        },
        width: options.width || 1.25,
      });

      points.forEach((point, index) => {
        if (index === 0 || index === points.length - 1 || index % 2 === 0) {
          vias.push({ x: point[0], y: point[1], r: options.viaSize || 4, phase: (point[0] + point[1]) * 0.01 });
        }
      });
    };

    add([[560, 230], [560, 168], [770, 168], [770, 118]], { delay: 0, mode: 'pingpong', speed: 0.0032 });
    add([[600, 230], [600, 148], [832, 148], [832, 118]], { delay: 16, mode: 'pingpong', speed: 0.0029 });
    add([[640, 252], [706, 252], [706, 196], [914, 196], [914, 118]], { delay: 34, mode: 'restart', speed: 0.0035 });
    add([[430, 342], [325, 342], [325, 205], [255, 205]], { delay: 20, mode: 'pingpong', speed: 0.0027 });
    add([[480, 466], [480, 610], [394, 610], [394, 666]], { delay: 42, mode: 'restart', speed: 0.0031 });
    add([[548, 466], [548, 630], [638, 630], [638, 666]], { delay: 58, mode: 'pingpong', speed: 0.0026 });
    add([[690, 408], [760, 408], [760, 540]], { delay: 26, mode: 'pingpong', speed: 0.0025 });
    add([[864, 594], [925, 594], [925, 565]], { delay: 76, mode: 'restart', speed: 0.0032 });
    add([[864, 622], [980, 622], [980, 578]], { delay: 84, mode: 'restart', speed: 0.0028 });
    add([[864, 566], [1130, 566], [1130, 520], [1220, 520]], { delay: 64, mode: 'pingpong', speed: 0.0023 });
    add([[864, 632], [1160, 632], [1160, 620], [1220, 620]], { delay: 96, mode: 'restart', speed: 0.0029 });
    add([[1130, 439], [1210, 439], [1210, 248], [1304, 248]], { delay: 70, mode: 'pingpong', speed: 0.0025 });
    add([[690, 300], [1040, 300], [1040, 412]], { delay: 48, mode: 'restart', speed: 0.003 });
    add([[235, 102], [184, 102], [184, 314], [430, 314]], { delay: 8, mode: 'pingpong', speed: 0.0024 });
    add([[136, 180], [255, 180], [430, 252]], { delay: 30, mode: 'restart', speed: 0.0036 });
    add([[730, 742], [1010, 742], [1010, 578]], { delay: 110, mode: 'pingpong', speed: 0.0022 });
    add([[210, 512], [318, 512], [318, 430], [430, 430]], { delay: 124, mode: 'pingpong', speed: 0.0025, width: 1.05 });
    add([[148, 628], [278, 628], [278, 566], [404, 566]], { delay: 142, mode: 'restart', speed: 0.003, width: 1 });
    add([[300, 258], [356, 258], [356, 356], [430, 356]], { delay: 132, mode: 'restart', speed: 0.0027, width: 1 });

    buildMicroDetails();
  }

  function buildMicroDetails() {
    const blocked = [
      { x: 430, y: 170, w: 640, h: 350 },
      { x: 430, y: 230, w: 260, h: 236 },
      { x: 698, y: 540, w: 166, h: 106 },
    ];
    const starts = [
      [96, 412], [118, 470], [155, 545], [210, 610], [300, 120], [350, 758],
      [310, 240], [292, 304], [338, 386], [376, 452], [326, 520], [404, 594],
      [980, 110], [1085, 160], [1180, 230], [1215, 330], [1145, 730], [1288, 742],
      [84, 710], [1170, 86], [1010, 690], [255, 748],
    ];

    starts.forEach((start, index) => {
      const direction = start[0] < project.w / 2 ? 1 : -1;
      const points = [start];
      let cx = start[0];
      let cy = start[1];
      const steps = 3 + (index % 3);

      for (let step = 0; step < steps; step += 1) {
        const horizontal = step % 2 === 0;
        if (horizontal) {
          cx += direction * (70 + ((index + step) % 4) * 28);
        } else {
          cy += (((index + step) % 2) === 0 ? 1 : -1) * (42 + ((index + step) % 3) * 18);
        }

        if (isBlocked(cx, cy, blocked)) break;
        points.push([Math.max(62, Math.min(1378, cx)), Math.max(68, Math.min(760, cy))]);
      }

      if (points.length > 1) {
        microTraces.push({ points, width: index % 4 === 0 ? 1.1 : 0.8 });
        const last = points[points.length - 1];
        vias.push({ x: last[0], y: last[1], r: 2.4, phase: (last[0] + last[1]) * 0.02 });
      }
    });

    [
      [176, 392, 42, 22], [248, 452, 54, 18], [318, 562, 36, 36], [364, 304, 58, 20],
      [386, 406, 46, 18], [336, 498, 72, 22], [402, 576, 42, 34], [980, 252, 62, 22],
      [1080, 322, 46, 18], [1160, 392, 38, 34], [1028, 708, 56, 20], [1210, 118, 48, 24],
      [280, 700, 72, 18], [104, 635, 44, 28], [1120, 670, 38, 38],
    ].forEach(([px, py, pw, ph]) => {
      microParts.push({ x: px, y: py, w: pw, h: ph, pins: Math.max(2, Math.floor(pw / 14)) });
    });
  }

  function isBlocked(px, py, zones) {
    return zones.some((zone) => px >= zone.x && px <= zone.x + zone.w && py >= zone.y && py <= zone.y + zone.h);
  }

  function pathLength(points) {
    let length = 0;
    for (let i = 1; i < points.length; i += 1) {
      length += Math.hypot(points[i][0] - points[i - 1][0], points[i][1] - points[i - 1][1]);
    }
    return length || 1;
  }

  function pointAt(points, distance) {
    let walked = 0;
    for (let i = 1; i < points.length; i += 1) {
      const a = points[i - 1];
      const b = points[i];
      const segment = Math.hypot(b[0] - a[0], b[1] - a[1]);
      if (walked + segment >= distance) {
        const t = (distance - walked) / segment;
        return [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t];
      }
      walked += segment;
    }

    return points[points.length - 1];
  }

  function colorAlpha(color, alpha) {
    const match = color.match(/rgba\((\d+),\s*(\d+),\s*(\d+),\s*[\d.]+\)/);
    if (!match) return color;
    return `rgba(${match[1]}, ${match[2]}, ${match[3]}, ${alpha})`;
  }

  function addExplosion(point, color, strength) {
    if (reduceMotion || explosions.length > 18) return;
    explosions.push({
      x: point[0],
      y: point[1],
      color,
      life: 0,
      maxLife: strength || 34,
      spokes: 7 + Math.floor(((point[0] + point[1]) % 5)),
    });
  }

  function addPortal(point, color, strength) {
    if (reduceMotion || portals.length > 24) return;
    portals.push({
      x: point[0],
      y: point[1],
      color,
      life: 0,
      maxLife: strength || 42,
      spin: ((point[0] + point[1]) % 6) * 0.18,
    });
  }

  function tracePath(points, limit) {
    const max = typeof limit === 'number' ? limit : pathLength(points);
    let walked = 0;
    ctx.beginPath();
    ctx.moveTo(x(points[0][0]), y(points[0][1]));

    for (let i = 1; i < points.length; i += 1) {
      const a = points[i - 1];
      const b = points[i];
      const segment = Math.hypot(b[0] - a[0], b[1] - a[1]);
      if (walked + segment >= max) {
        const t = Math.max(0, Math.min(1, (max - walked) / segment));
        ctx.lineTo(x(a[0] + (b[0] - a[0]) * t), y(a[1] + (b[1] - a[1]) * t));
        return;
      }
      ctx.lineTo(x(b[0]), y(b[1]));
      walked += segment;
    }
  }

  function traceSegment(points, fromDistance, toDistance) {
    const start = Math.max(0, Math.min(fromDistance, toDistance));
    const end = Math.max(0, Math.max(fromDistance, toDistance));
    let walked = 0;
    let started = false;

    ctx.beginPath();

    for (let i = 1; i < points.length; i += 1) {
      const a = points[i - 1];
      const b = points[i];
      const segment = Math.hypot(b[0] - a[0], b[1] - a[1]);
      const segmentStart = walked;
      const segmentEnd = walked + segment;

      if (segmentEnd < start) {
        walked += segment;
        continue;
      }

      if (segmentStart > end) {
        break;
      }

      const localStart = Math.max(start, segmentStart);
      const localEnd = Math.min(end, segmentEnd);
      const t0 = segment === 0 ? 0 : (localStart - segmentStart) / segment;
      const t1 = segment === 0 ? 0 : (localEnd - segmentStart) / segment;
      const sx0 = a[0] + (b[0] - a[0]) * t0;
      const sy0 = a[1] + (b[1] - a[1]) * t0;
      const sx1 = a[0] + (b[0] - a[0]) * t1;
      const sy1 = a[1] + (b[1] - a[1]) * t1;

      if (!started) {
        ctx.moveTo(x(sx0), y(sy0));
        started = true;
      } else {
        ctx.lineTo(x(sx0), y(sy0));
      }
      ctx.lineTo(x(sx1), y(sy1));

      walked += segment;
    }
  }

  function drawRect(part) {
    ctx.fillStyle = palette.boardDeep;
    ctx.strokeStyle = 'rgba(34, 211, 238, 0.2)';
    ctx.lineWidth = s(1);
    ctx.fillRect(x(part.x), y(part.y), s(part.w), s(part.h));
    ctx.strokeRect(x(part.x), y(part.y), s(part.w), s(part.h));
  }

  function label(text, px, py, size) {
    ctx.fillStyle = palette.silk;
    ctx.font = `${s(size || 12)}px Rajdhani, sans-serif`;
    ctx.fillText(text, x(px), y(py));
  }

  function drawBoardFrame() {
    ctx.fillStyle = palette.bg;
    ctx.fillRect(0, 0, width, height);

    ctx.fillStyle = 'rgba(2, 12, 23, 0.76)';
    ctx.strokeStyle = 'rgba(34, 211, 238, 0.12)';
    ctx.lineWidth = s(2);
    ctx.fillRect(x(40), y(42), s(1360), s(744));
    ctx.strokeRect(x(40), y(42), s(1360), s(744));

    ctx.strokeStyle = 'rgba(34, 211, 238, 0.06)';
    ctx.lineWidth = s(1);
    for (let gx = 80; gx < 1380; gx += 80) {
      ctx.beginPath();
      ctx.moveTo(x(gx), y(58));
      ctx.lineTo(x(gx), y(770));
      ctx.stroke();
    }
    for (let gy = 86; gy < 760; gy += 74) {
      ctx.beginPath();
      ctx.moveTo(x(58), y(gy));
      ctx.lineTo(x(1384), y(gy));
      ctx.stroke();
    }

    [[72, 72], [1368, 72], [72, 756], [1368, 756]].forEach(([px, py]) => {
      ctx.beginPath();
      ctx.arc(x(px), y(py), s(9), 0, Math.PI * 2);
      ctx.strokeStyle = 'rgba(125, 211, 252, 0.16)';
      ctx.stroke();
      ctx.beginPath();
      ctx.arc(x(px), y(py), s(3), 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(0, 0, 0, 0.8)';
      ctx.fill();
    });
  }

  function drawParts() {
    for (const part of parts) {
      drawRect(part);

      if (part.type === 'cpu') {
        ctx.strokeStyle = 'rgba(34, 211, 238, 0.14)';
        for (let i = 1; i <= 3; i += 1) {
          ctx.strokeRect(x(part.x + i * 18), y(part.y + i * 18), s(part.w - i * 36), s(part.h - i * 36));
        }
        ctx.fillStyle = palette.copper;
        for (let row = 0; row < 10; row += 1) {
          for (let col = 0; col < 11; col += 1) {
            ctx.beginPath();
            ctx.arc(x(part.x + 48 + col * 16), y(part.y + 44 + row * 15), s(1.4), 0, Math.PI * 2);
            ctx.fill();
          }
        }
      }

      if (part.type === 'ram') {
        for (let i = 0; i < 4; i += 1) {
          const rx = part.x + i * 46;
          ctx.fillStyle = i % 2 === 0 ? 'rgba(6, 35, 45, 0.64)' : 'rgba(4, 28, 39, 0.64)';
          ctx.strokeStyle = 'rgba(34, 211, 238, 0.22)';
          ctx.fillRect(x(rx), y(part.y), s(28), s(part.h));
          ctx.strokeRect(x(rx), y(part.y), s(28), s(part.h));
          ctx.fillStyle = palette.copper;
          for (let p = 0; p < 19; p += 1) {
            ctx.fillRect(x(rx + 5), y(part.y + 34 + p * 16), s(18), s(6));
          }
        }
      }

      if (part.type === 'vrm') {
        for (let i = 0; i < 8; i += 1) {
          ctx.strokeStyle = 'rgba(125, 211, 252, 0.12)';
          ctx.strokeRect(x(part.x + 12 + i * 35), y(part.y + 8), s(24), s(36));
        }
      }

      if (part.type === 'pcie' || part.type === 'm2') {
        ctx.fillStyle = 'rgba(4, 12, 24, 0.72)';
        ctx.fillRect(x(part.x + 8), y(part.y + 7), s(part.w - 16), s(Math.max(8, part.h - 14)));
      }

      if (part.type === 'sata') {
        for (let i = 0; i < 5; i += 1) {
          ctx.strokeStyle = 'rgba(34, 211, 238, 0.2)';
          ctx.strokeRect(x(part.x + 10), y(part.y + 12 + i * 38), s(36), s(26));
        }
      }

      label(part.label, part.x + 10, part.y + part.h + 18, 11);
    }
  }

  function drawMicroDetails() {
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    for (const trace of microTraces) {
      tracePath(trace.points);
      ctx.strokeStyle = palette.microTrace;
      ctx.lineWidth = s(trace.width);
      ctx.stroke();
    }

    for (const part of microParts) {
      ctx.fillStyle = 'rgba(8, 47, 73, 0.14)';
      ctx.strokeStyle = 'rgba(125, 211, 252, 0.12)';
      ctx.lineWidth = s(0.8);
      ctx.fillRect(x(part.x), y(part.y), s(part.w), s(part.h));
      ctx.strokeRect(x(part.x), y(part.y), s(part.w), s(part.h));

      ctx.fillStyle = 'rgba(251, 146, 60, 0.13)';
      for (let i = 0; i < part.pins; i += 1) {
        const px = part.x + 5 + i * ((part.w - 10) / Math.max(1, part.pins - 1));
        ctx.fillRect(x(px), y(part.y + part.h - 4), s(4), s(3));
      }
    }
  }

  function drawTraces() {
    for (const trace of traces) {
      if (!reduceMotion && frame < trace.delay) continue;

      if (trace.draw < 1) {
        trace.draw = Math.min(1, trace.draw + trace.drawSpeed);
      }

      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = palette.trace;
      ctx.lineWidth = s(trace.width);
      tracePath(trace.points);
      ctx.stroke();

      ctx.strokeStyle = palette.traceDraw;
      ctx.lineWidth = s(trace.width + 0.35);
      tracePath(trace.points, trace.length * trace.draw);
      ctx.stroke();
    }
  }

  function drawVias(now) {
    for (const via of vias) {
      const glow = 0.5 + Math.sin(now * 0.0014 + via.phase) * 0.5;
      ctx.beginPath();
      ctx.arc(x(via.x), y(via.y), s(via.r + 2), 0, Math.PI * 2);
      ctx.strokeStyle = `rgba(125, 211, 252, ${0.08 + glow * 0.12})`;
      ctx.lineWidth = s(1);
      ctx.stroke();

      ctx.beginPath();
      ctx.arc(x(via.x), y(via.y), s(via.r), 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(2, 6, 23, 0.9)';
      ctx.fill();
      ctx.strokeStyle = 'rgba(34, 211, 238, 0.26)';
      ctx.stroke();
    }
  }

  function advancePulse(trace) {
    const pulse = trace.pulse;
    if (trace.draw < 1 || reduceMotion) return;

    if (pulse.collisionCooldown > 0) {
      pulse.collisionCooldown -= 1;
    }

    if (pulse.wait > 0) {
      pulse.wait -= 1;
      return;
    }

    pulse.t += pulse.dir * pulse.speed;

    if (pulse.mode === 'pingpong') {
      if (pulse.t >= 1) {
        pulse.t = 1;
        pulse.dir = -1;
        pulse.wait = pulse.waitMax;
        pulse.hidden = Math.max(pulse.hidden, pulse.waitMax);
        addPortal(pointAt(trace.points, trace.length), pulse.color, 42);
      } else if (pulse.t <= 0) {
        pulse.t = 0;
        pulse.dir = 1;
        pulse.wait = pulse.waitMax;
        pulse.hidden = Math.max(pulse.hidden, pulse.waitMax);
        addPortal(pointAt(trace.points, 0), pulse.color, 38);
      }
      return;
    }

    if (pulse.t >= 1) {
      addPortal(pointAt(trace.points, trace.length), pulse.color, 46);
      pulse.t = 0;
      pulse.wait = pulse.waitMax;
      pulse.hidden = Math.max(pulse.hidden, pulse.waitMax + 18);
    }
  }

  function drawPulses() {
    const activeHeads = [];

    for (const trace of traces) {
      advancePulse(trace);
      if (trace.draw < 1) continue;

      const pulse = trace.pulse;
      if (pulse.hidden > 0) {
        pulse.hidden -= 1;
        pulse.crashed = false;
        continue;
      }

      const headDistance = trace.length * pulse.t;
      const tailLength = Math.min(trace.length * 0.16, 58);
      const tailDistance = pulse.dir >= 0
        ? Math.max(0, headDistance - tailLength)
        : Math.min(trace.length, headDistance + tailLength);
      const head = pointAt(trace.points, headDistance);
      activeHeads.push({
        x: head[0],
        y: head[1],
        color: pulse.color,
        pulse,
        trace,
        trailStart: tailDistance,
        trailEnd: headDistance,
      });

      if (pulse.crashed) {
        pulse.crashed = false;
        continue;
      }

      traceSegment(trace.points, tailDistance, headDistance);
      ctx.strokeStyle = colorAlpha(pulse.color, 0.2);
      ctx.lineWidth = s(6);
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.stroke();

      traceSegment(trace.points, tailDistance, headDistance);
      ctx.strokeStyle = pulse.color;
      ctx.lineWidth = s(2.4);
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.stroke();

      ctx.beginPath();
      ctx.arc(x(head[0]), y(head[1]), s(3.1), 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(255, 255, 255, 0.95)';
      ctx.fill();

      ctx.beginPath();
      ctx.arc(x(head[0]), y(head[1]), s(10), 0, Math.PI * 2);
      ctx.fillStyle = colorAlpha(pulse.color, 0.12);
      ctx.fill();
    }

    detectPulseCollisions(activeHeads);
  }

  function detectPulseCollisions(activeHeads) {
    if (frame % 4 !== 0) return;

    for (let i = 0; i < activeHeads.length; i += 1) {
      const a = activeHeads[i];
      if (a.pulse.collisionCooldown > 0) continue;

      for (let j = i + 1; j < activeHeads.length; j += 1) {
        const b = activeHeads[j];
        if (b.pulse.collisionCooldown > 0) continue;

        const distance = Math.hypot(a.x - b.x, a.y - b.y);
        if (distance <= 16) {
          const point = [(a.x + b.x) / 2, (a.y + b.y) / 2];
          addExplosion(point, a.color, 38);
          crashPulse(a);
          crashPulse(b);
          break;
        }

        const aHitTrail = pointNearTraceSegment(a.x, a.y, b.trailStart, b.trailEnd, b.trace);
        const bHitTrail = pointNearTraceSegment(b.x, b.y, a.trailStart, a.trailEnd, a.trace);
        if (!aHitTrail && !bHitTrail) continue;

        if (aHitTrail) {
          addExplosion([a.x, a.y], a.color, 38);
          crashPulse(a);
        }

        if (bHitTrail) {
          addExplosion([b.x, b.y], b.color, 38);
          crashPulse(b);
        }

        break;
      }
    }
  }

  function crashPulse(active) {
    active.pulse.crashed = true;
    active.pulse.collisionCooldown = 120;
    active.pulse.wait = Math.max(active.pulse.wait, active.pulse.waitMax + 38);
    active.pulse.hidden = Math.max(active.pulse.hidden, 48);

    if (active.pulse.mode === 'pingpong') {
      active.pulse.t = active.pulse.dir >= 0 ? 0 : 1;
      return;
    }

    active.pulse.t = 0;
  }

  function pointNearTraceSegment(px, py, fromDistance, toDistance, trace) {
    const start = Math.max(0, Math.min(fromDistance, toDistance));
    const end = Math.min(trace.length, Math.max(fromDistance, toDistance));
    let walked = 0;

    for (let i = 1; i < trace.points.length; i += 1) {
      const a = trace.points[i - 1];
      const b = trace.points[i];
      const segment = Math.hypot(b[0] - a[0], b[1] - a[1]);
      const segmentStart = walked;
      const segmentEnd = walked + segment;

      if (segmentEnd < start) {
        walked += segment;
        continue;
      }

      if (segmentStart > end) {
        break;
      }

      const localStart = Math.max(start, segmentStart);
      const localEnd = Math.min(end, segmentEnd);
      const t0 = segment === 0 ? 0 : (localStart - segmentStart) / segment;
      const t1 = segment === 0 ? 0 : (localEnd - segmentStart) / segment;
      const ax = a[0] + (b[0] - a[0]) * t0;
      const ay = a[1] + (b[1] - a[1]) * t0;
      const bx = a[0] + (b[0] - a[0]) * t1;
      const by = a[1] + (b[1] - a[1]) * t1;

      if (distanceToSegment(px, py, ax, ay, bx, by) <= 12) {
        return true;
      }

      walked += segment;
    }

    return false;
  }

  function distanceToSegment(px, py, ax, ay, bx, by) {
    const dx = bx - ax;
    const dy = by - ay;
    const lengthSq = dx * dx + dy * dy;
    if (lengthSq === 0) return Math.hypot(px - ax, py - ay);

    const t = Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / lengthSq));
    const cx = ax + dx * t;
    const cy = ay + dy * t;
    return Math.hypot(px - cx, py - cy);
  }

  function drawExplosions() {
    explosions = explosions.filter((explosion) => explosion.life < explosion.maxLife);

    for (const explosion of explosions) {
      const progress = explosion.life / explosion.maxLife;
      const fade = 1 - progress;
      const radius = s(6 + progress * 34);

      ctx.beginPath();
      ctx.arc(x(explosion.x), y(explosion.y), radius, 0, Math.PI * 2);
      ctx.strokeStyle = colorAlpha(explosion.color, 0.42 * fade);
      ctx.lineWidth = s(1.4);
      ctx.stroke();

      for (let i = 0; i < explosion.spokes; i += 1) {
        const angle = (Math.PI * 2 * i) / explosion.spokes + progress * 0.5;
        const inner = s(4 + progress * 8);
        const outer = s(12 + progress * 42);
        ctx.beginPath();
        ctx.moveTo(x(explosion.x) + Math.cos(angle) * inner, y(explosion.y) + Math.sin(angle) * inner);
        ctx.lineTo(x(explosion.x) + Math.cos(angle) * outer, y(explosion.y) + Math.sin(angle) * outer);
        ctx.strokeStyle = colorAlpha(explosion.color, 0.56 * fade);
        ctx.lineWidth = s(1.2);
        ctx.stroke();
      }

      ctx.beginPath();
      ctx.arc(x(explosion.x), y(explosion.y), s(3 + progress * 8), 0, Math.PI * 2);
      ctx.fillStyle = colorAlpha(explosion.color, 0.22 * fade);
      ctx.fill();
      explosion.life += 1;
    }
  }

  function drawPortals() {
    portals = portals.filter((portal) => portal.life < portal.maxLife);

    for (const portal of portals) {
      const progress = portal.life / portal.maxLife;
      const fade = 1 - progress;
      const radius = s(5 + Math.sin(progress * Math.PI) * 20);
      const rotation = portal.spin + progress * Math.PI * 1.8;

      ctx.save();
      ctx.translate(x(portal.x), y(portal.y));
      ctx.rotate(rotation);

      for (let ring = 0; ring < 3; ring += 1) {
        ctx.beginPath();
        ctx.ellipse(0, 0, radius + s(ring * 5), radius * (0.42 + ring * 0.08), 0, 0, Math.PI * 2);
        ctx.strokeStyle = colorAlpha(portal.color, (0.52 - ring * 0.12) * fade);
        ctx.lineWidth = s(1.2);
        ctx.stroke();
      }

      ctx.beginPath();
      ctx.arc(0, 0, s(3 + progress * 5), 0, Math.PI * 2);
      ctx.fillStyle = colorAlpha(portal.color, 0.22 * fade);
      ctx.fill();

      ctx.restore();
      portal.life += 1;
    }
  }

  function draw() {
    const now = performance.now();
    frame += 1;

    drawBoardFrame();
    drawMicroDetails();
    drawTraces();
    drawParts();
    drawVias(now);
    drawPulses();
    drawPortals();
    drawExplosions();

    if (!reduceMotion) {
      window.requestAnimationFrame(draw);
    }
  }

  window.addEventListener('resize', resize, { passive: true });
  resize();
}());

(function () {
  const root = document.querySelector('[data-preview-carousel]');
  if (!root) return;

  const slides = Array.from(root.querySelectorAll('[data-preview-carousel-slide]'));
  const dots = Array.from(root.querySelectorAll('[data-preview-carousel-dot]'));
  const prev = document.querySelector('[data-preview-carousel-prev]');
  const next = document.querySelector('[data-preview-carousel-next]');
  if (slides.length <= 1) return;

  let current = 0;
  let timer = null;

  function goTo(index) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, slideIndex) => {
      slide.classList.toggle('is-active', slideIndex === current);
    });
    dots.forEach((dot, dotIndex) => {
      dot.classList.toggle('is-active', dotIndex === current);
    });
  }

  function stopAutoPlay() {
    if (timer) {
      window.clearInterval(timer);
      timer = null;
    }
  }

  function startAutoPlay() {
    stopAutoPlay();
    timer = window.setInterval(() => goTo(current + 1), 5200);
  }

  if (prev) {
    prev.addEventListener('click', () => {
      goTo(current - 1);
      startAutoPlay();
    });
  }

  if (next) {
    next.addEventListener('click', () => {
      goTo(current + 1);
      startAutoPlay();
    });
  }

  dots.forEach((dot) => {
    dot.addEventListener('click', () => {
      goTo(parseInt(dot.getAttribute('data-preview-carousel-dot') || '0', 10));
      startAutoPlay();
    });
  });

  root.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowLeft') {
      goTo(current - 1);
      startAutoPlay();
    }
    if (event.key === 'ArrowRight') {
      goTo(current + 1);
      startAutoPlay();
    }
  });

  root.addEventListener('mouseenter', stopAutoPlay);
  root.addEventListener('mouseleave', startAutoPlay);
  root.addEventListener('focusin', stopAutoPlay);
  root.addEventListener('focusout', startAutoPlay);
  startAutoPlay();
}());
