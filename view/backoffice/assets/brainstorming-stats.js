(function () {
  const stats = window.brainstormingStats || {};

  function prepareCanvas(canvas, fallbackWidth, fallbackHeight) {
    const rect = canvas.getBoundingClientRect();
    const parentWidth = canvas.parentElement ? canvas.parentElement.clientWidth : 0;
    const cssWidth = Math.max(280, Math.floor(rect.width || parentWidth || fallbackWidth));
    const cssHeight = Math.max(220, Math.floor(rect.height || Number(canvas.getAttribute("height")) || fallbackHeight));
    const ratio = window.devicePixelRatio || 1;

    canvas.width = Math.floor(cssWidth * ratio);
    canvas.height = Math.floor(cssHeight * ratio);

    const ctx = canvas.getContext("2d");
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

    return { ctx, width: cssWidth, height: cssHeight };
  }

  function drawLineChart(canvas, labels, primary, secondary) {
    if (!canvas) return;

    const { ctx, width, height } = prepareCanvas(canvas, 900, 260);
    const padding = { top: 34, right: 22, bottom: 34, left: 34 };
    const values = [...primary, ...secondary, 1];
    const max = Math.max(...values);
    const steps = Math.max(1, max);
    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;

    ctx.clearRect(0, 0, width, height);
    ctx.font = "12px Outfit, sans-serif";
    ctx.lineWidth = 1;

    for (let i = 0; i <= steps; i += 1) {
      const y = padding.top + plotHeight - (plotHeight * i) / steps;
      ctx.strokeStyle = "rgba(148, 163, 184, 0.12)";
      ctx.beginPath();
      ctx.moveTo(padding.left, y);
      ctx.lineTo(width - padding.right, y);
      ctx.stroke();
      ctx.fillStyle = "rgba(194, 210, 255, 0.78)";
      ctx.fillText(String(i), 10, y + 4);
    }

    function point(index, value) {
      const x = padding.left + (labels.length <= 1 ? 0 : (plotWidth * index) / (labels.length - 1));
      const y = padding.top + plotHeight - (plotHeight * value) / steps;
      return { x, y };
    }

    function drawSeries(data, color) {
      ctx.strokeStyle = color;
      ctx.lineWidth = 3;
      ctx.beginPath();
      data.forEach((value, index) => {
        const p = point(index, value);
        if (index === 0) ctx.moveTo(p.x, p.y);
        else ctx.lineTo(p.x, p.y);
      });
      ctx.stroke();

      data.forEach((value, index) => {
        const p = point(index, value);
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.arc(p.x, p.y, 5, 0, Math.PI * 2);
        ctx.fill();
      });
    }

    drawSeries(primary, "#6d5cff");
    drawSeries(secondary, "#ff6fd8");

    ctx.fillStyle = "rgba(194, 210, 255, 0.78)";
    labels.forEach((label, index) => {
      const p = point(index, 0);
      ctx.fillText(label, p.x - 14, height - 10);
    });

    ctx.fillStyle = "#6d5cff";
    ctx.fillRect(width / 2 - 95, 14, 28, 10);
    ctx.fillStyle = "rgba(194, 210, 255, 0.78)";
    ctx.fillText("Brainstormings", width / 2 - 60, 23);
    ctx.fillStyle = "#ff6fd8";
    ctx.fillRect(width / 2 + 70, 14, 28, 10);
    ctx.fillStyle = "rgba(194, 210, 255, 0.78)";
    ctx.fillText("Idees", width / 2 + 105, 23);
  }

  function drawBarChart(canvas, labels, values) {
    if (!canvas) return;

    const { ctx, width, height } = prepareCanvas(canvas, 620, 260);
    const padding = { top: 36, right: 18, bottom: 26, left: 100 };
    const max = Math.max(...values, 1);
    const plotWidth = width - padding.left - padding.right;
    const rowHeight = (height - padding.top - padding.bottom) / Math.max(labels.length, 1);
    const colors = ["#6d5cff", "#ff6fd8", "#2dd4bf", "#f6b84b", "#60a5fa", "#f97316"];

    ctx.clearRect(0, 0, width, height);
    ctx.font = "12px Outfit, sans-serif";
    ctx.fillStyle = "#6d5cff";
    ctx.fillRect(width / 2 - 32, 14, 28, 10);
    ctx.fillStyle = "rgba(194, 210, 255, 0.78)";
    ctx.fillText("Nombre", width / 2 + 2, 23);

    labels.forEach((label, index) => {
      const y = padding.top + index * rowHeight + rowHeight * 0.25;
      const barWidth = (plotWidth * values[index]) / max;
      ctx.fillStyle = "rgba(194, 210, 255, 0.78)";
      ctx.fillText(label || "Autre", 10, y + rowHeight * 0.25);
      ctx.fillStyle = colors[index % colors.length];
      roundedRect(ctx, padding.left, y, barWidth, rowHeight * 0.52, 7);
      ctx.fill();
    });
  }

  function roundedRect(ctx, x, y, width, height, radius) {
    const safeRadius = Math.min(radius, height / 2, width / 2);
    ctx.beginPath();
    ctx.moveTo(x + safeRadius, y);
    ctx.lineTo(x + width - safeRadius, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + safeRadius);
    ctx.lineTo(x + width, y + height - safeRadius);
    ctx.quadraticCurveTo(x + width, y + height, x + width - safeRadius, y + height);
    ctx.lineTo(x + safeRadius, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - safeRadius);
    ctx.lineTo(x, y + safeRadius);
    ctx.quadraticCurveTo(x, y, x + safeRadius, y);
  }

  function render() {
    drawLineChart(
      document.getElementById("monthly-chart"),
      stats.monthlyLabels || [],
      stats.monthlyBrainstormings || [],
      stats.monthlyIdeas || []
    );
    drawBarChart(
      document.getElementById("category-chart"),
      stats.categoryLabels || [],
      stats.categoryValues || []
    );
  }

  render();
  window.addEventListener("resize", render);
})();
