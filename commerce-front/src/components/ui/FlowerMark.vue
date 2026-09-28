<template>
  <!-- Marque florale (placeholder decoratif) -->
  <svg
    class="flower-mark"
    viewBox="0 0 48 48"
    fill="none"
    aria-hidden="true"
  >
    <g transform="translate(24 24)">
      <!-- 5 pétales -->
      <g fill="currentColor" opacity="0.9">
        <ellipse cx="0" cy="-13" rx="6.5" ry="11" />
        <ellipse cx="0" cy="-13" rx="6.5" ry="11" transform="rotate(72)" />
        <ellipse cx="0" cy="-13" rx="6.5" ry="11" transform="rotate(144)" />
        <ellipse cx="0" cy="-13" rx="6.5" ry="11" transform="rotate(216)" />
        <ellipse cx="0" cy="-13" rx="6.5" ry="11" transform="rotate(288)" />
      </g>
      <!-- Cœur -->
      <circle r="6" fill="#fff" opacity="0.55" />
      <circle r="3" fill="currentColor" />
    </g>
  </svg>
</template>

<style scoped>
.flower-mark {
  width: 1em;
  height: 1em;
  display: inline-block;
  color: var(--rose-400, #f06292);
}
</style>
