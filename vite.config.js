// vite.config.js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/app.css',
        'resources/css/filament/theme.css',   // add if you use ->viteTheme(...)
        'resources/js/app.js',
      ],
      refresh: true,
    }),
  ],
})
