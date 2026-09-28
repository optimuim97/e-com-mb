import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,

  // Arrêt sur premier échec en CI
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,

  reporter: [
    ['html', { outputFolder: 'tests/e2e/report', open: 'never' }],
    ['line'],
  ],

  use: {
    baseURL: 'http://localhost:5174',
    // Capture screenshot + trace sur échec uniquement
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
    video: 'off',
    // Simule un mobile ivoirien par défaut (cible principale)
    ...devices['Pixel 7'],
    locale: 'fr-FR',
    timezoneId: 'Africa/Abidjan',
  },

  projects: [
    {
      name: 'mobile-ci',
      use: { ...devices['Pixel 7'] },
    },
    {
      name: 'desktop',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  // Lance le dev server automatiquement si pas déjà démarré
  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:5174',
    reuseExistingServer: true,
    timeout: 60_000,
  },
})
