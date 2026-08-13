(() => {
    const toneColor = (tone) => {
        switch (tone) {
            case 'red':
                return '#ef4444';
            case 'orange':
                return '#f97316';
            case 'yellow':
                return '#eab308';
            case 'lime':
                return '#84cc16';
            case 'green':
                return '#16a34a';
            default:
                return '#64748b';
        }
    };

    const needleAngle = (percent) => {
        const start = Math.PI;
        const span = Math.PI;
        const capped = Math.max(0, Number(percent) || 0);

        if (capped <= 100) {
            return start + (span * (capped / 100));
        }

        const over = Math.min(0.1, ((capped - 100) / 100) * 0.35);

        return start + span + (span * over);
    };

    const drawArrowNeedle = (ctx, cx, cy, angle, length, color) => {
        const tipX = cx + Math.cos(angle) * length;
        const tipY = cy + Math.sin(angle) * length;
        const head = Math.max(6, length * 0.18);
        const shaft = Math.max(2, length * 0.055);
        const headAngle = Math.PI / 7;

        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(
            tipX - Math.cos(angle) * (head * 0.55),
            tipY - Math.sin(angle) * (head * 0.55),
        );
        ctx.strokeStyle = color;
        ctx.lineWidth = shaft;
        ctx.lineCap = 'round';
        ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(tipX, tipY);
        ctx.lineTo(
            tipX - head * Math.cos(angle - headAngle),
            tipY - head * Math.sin(angle - headAngle),
        );
        ctx.lineTo(
            tipX - head * Math.cos(angle + headAngle),
            tipY - head * Math.sin(angle + headAngle),
        );
        ctx.closePath();
        ctx.fillStyle = color;
        ctx.fill();

        const hub = Math.max(3.2, length * 0.09);
        ctx.beginPath();
        ctx.arc(cx, cy, hub, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();

        ctx.beginPath();
        ctx.arc(cx, cy, hub * 0.42, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();
    };

    const drawGauge = (canvas) => {
        if (!canvas?.getContext) {
            return;
        }

        const percent = Number(canvas.dataset.percent || 0);
        const tone = canvas.dataset.tone || 'slate';
        const needleColor = toneColor(tone);
        const dpr = window.devicePixelRatio || 1;
        const cssWidth = canvas.clientWidth || 280;
        const cssHeight = canvas.clientHeight || 160;

        canvas.width = Math.round(cssWidth * dpr);
        canvas.height = Math.round(cssHeight * dpr);

        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, cssWidth, cssHeight);

        const padTop = 6;
        const approxR = cssWidth * 0.38;
        const approxTrack = Math.max(8, approxR * 0.22);
        const hubPad = Math.ceil(approxTrack / 2 + 7);
        const cx = cssWidth / 2;
        const cy = Math.max(hubPad, cssHeight - hubPad);
        const radius = Math.min(
            approxR,
            Math.max(12, cy - padTop - (approxTrack / 2)),
        );
        const start = Math.PI;
        const trackWidth = Math.max(8, radius * 0.22);

        ctx.beginPath();
        ctx.arc(cx, cy, radius, start, Math.PI * 2);
        ctx.strokeStyle = '#d6dee9';
        ctx.lineWidth = trackWidth + 3;
        ctx.lineCap = 'butt';
        ctx.stroke();

        const zones = [
            { from: 0, to: 0.2, color: '#ef4444' },
            { from: 0.2, to: 0.4, color: '#f97316' },
            { from: 0.4, to: 0.6, color: '#eab308' },
            { from: 0.6, to: 0.8, color: '#84cc16' },
            { from: 0.8, to: 1, color: '#16a34a' },
        ];

        zones.forEach((zone) => {
            ctx.beginPath();
            ctx.arc(cx, cy, radius, start + (Math.PI * zone.from), start + (Math.PI * zone.to));
            ctx.strokeStyle = zone.color;
            ctx.lineWidth = trackWidth;
            ctx.lineCap = 'butt';
            ctx.stroke();
        });

        const needleLength = radius * 0.72;
        drawArrowNeedle(ctx, cx, cy, needleAngle(percent), needleLength, needleColor);
    };

    const init = () => {
        document.querySelectorAll('[data-gestor-gauge-canvas]').forEach((canvas) => drawGauge(canvas));
    };

    window.UnitecGestorRefreshGauges = init;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('livewire:navigated', init);
})();
