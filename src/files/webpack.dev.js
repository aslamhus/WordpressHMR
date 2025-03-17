const path = require('path');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');
const common = require('./webpack.common');
const { merge } = require('webpack-merge');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
/**
 * Get the assets.json file config
 */
const assetsJson = require('./resources/assets/assets.json');
const { theme } = assetsJson.config;
// create proxy i.e. http://local.coryweeds:8888
const { host, port, protocol } = assetsJson.config;
const proxy = `${protocol || 'http'}://${host}${port ? `:${port}` : ''}`;
module.exports = merge(common, {
  mode: 'development',
  devtool: 'source-map', // source-map for no eval
  output: {
    hotUpdateChunkFilename: 'hmr/[id].[fullhash].hot-update.js',
    hotUpdateMainFilename: 'hmr/[runtime].[fullhash].hot-update.json',
  },
  plugins: [
    new BrowserSyncPlugin(
      {
        proxy,
        files: [
          {
            match: ['**/*.php'],
            fn: function (event, file) {
              if (event === 'change') {
                return;
                const bs = require('browser-sync').get('bs-webpack-plugin');
                bs.reload();
              }
            },
          },
        ],
      },
      {
        reload: false,
      }
    ),
  ],
  devServer: {
    // ...defaultConfig.devServer,
    liveReload: false,
    devMiddleware: {
      index: true,
      publicPath: path.resolve(path.resolve(__dirname) + '/public' + theme),
      serverSideRender: false,
      writeToDisk: (filePath) => {
        console.log('********** filePath', filePath);
        return true;
      },
    },
    static: {
      directory: path.resolve(__dirname, '/'),
      staticOptions: {},
      publicPath: '/',
    },
    open: {
      target: [proxy],
    },
    // compress: true,
    hot: true,
    host,
    headers: {
      'Access-Control-Allow-Origin': '*',
    },
    port: 8080,
    https: false,
  },
});
