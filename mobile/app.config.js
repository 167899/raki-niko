const fs = require('fs');
const path = require('path');

// Firebase (FCM) для push-сповіщень на Android: файл з консолі Firebase, без нього застосунок просто не отримує сповіщень.
const GOOGLE_SERVICES = './google-services.json';

module.exports = ({ config }) => {
  if (fs.existsSync(path.join(__dirname, GOOGLE_SERVICES))) {
    config = { ...config, android: { ...config.android, googleServicesFile: GOOGLE_SERVICES } };
  }
  // Вебверсія з демо-даними публікується на GitHub Pages у підкаталозі репозиторію.
  return process.env.EXPO_PUBLIC_DEMO === '1' ? { ...config, experiments: { ...config.experiments, baseUrl: '/raki-niko' } } : config;
};
