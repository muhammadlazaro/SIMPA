import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

function stripIddsExternalFontImport() {
  return {
    name: 'strip-idds-external-font-import',
    enforce: 'pre',
    transform(code, id) {
      if (!id.includes('@idds') || !id.endsWith('.css')) return null

      return code.replace(
        /@import\s*(?:url\()?['"]https:\/\/fonts\.googleapis\.com\/css2\?family=Inter[^'"]*['"]\)?;?/g,
        '',
      )
    },
  }
}

// https://vite.dev/config/
export default defineConfig({
  plugins: [stripIddsExternalFontImport(), vue()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/tests/setup.js',
    css: true,
  },
})
