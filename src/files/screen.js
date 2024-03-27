import '../assets/css/screen.scss';

console.info('screen.js loaded');

if (module?.hot) {
  console.log('module.hot is available.');
  module.hot.accept();
}
