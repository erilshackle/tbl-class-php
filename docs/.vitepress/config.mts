import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Tbl::class',
  description: 'PHP constants from your database schema. Keep writing SQL with names your IDE knows.',
  lang: 'en-US',
  base: '/tbl-class-php/',
  lastUpdated: true,
  themeConfig: {
    nav: [
      { text: 'Guide', link: '/getting-started' },
      { text: 'CLI', link: '/cli' },
      { text: 'v2.0.0', items: [
        { text: 'Migration guide', link: '/migration-v2' },
        { text: 'Changelog', link: 'https://github.com/erilshackle/tbl-class-php/blob/main/CHANGELOG.md' }
      ] }
    ],
    sidebar: [
      { text: 'Get started', items: [
        { text: 'Overview', link: '/overview' },
        { text: 'Installation & first generation', link: '/getting-started' },
        { text: 'Autoloading', link: '/autoload' }
      ] },
      { text: 'Use Tbl::class', items: [
        { text: 'Configuration', link: '/configuration' },
        { text: 'Naming strategies', link: '/naming' },
        { text: 'Constants, aliases & JOINs', link: '/usage' }
      ] },
      { text: 'Commands & maintenance', items: [
        { text: 'CLI reference', link: '/cli' },
        { text: 'Check & diff', link: '/check' },
        { text: 'Independence', link: '/independence' },
        { text: 'Migrate to v2', link: '/migration-v2' },
        { text: 'Contributing', link: '/contributing' }
      ] }
    ],
    search: { provider: 'local' },
    outline: [2, 3],
    socialLinks: [{ icon: 'github', link: 'https://github.com/erilshackle/tbl-class-php' }],
    editLink: { pattern: 'https://github.com/erilshackle/tbl-class-php/edit/main/docs/:path' },
    footer: { message: 'Released under the MIT License.', copyright: 'Copyright © Eril TS Carvalho' }
  }
})
