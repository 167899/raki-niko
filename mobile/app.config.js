// Вебверсія з демо-даними публікується на GitHub Pages у підкаталозі репозиторію.
module.exports = ({ config }) =>
  process.env.EXPO_PUBLIC_DEMO === '1' ? { ...config, experiments: { ...config.experiments, baseUrl: '/raki-niko' } } : config;
