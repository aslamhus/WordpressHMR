const path = require("path");
const BrowserSyncPlugin = require("browser-sync-webpack-plugin");
const common = require("./webpack.common");
const { merge } = require("webpack-merge");
const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const WORKING_DIR = process.env.WORKING_DIR;
/**
 * Get the whr.json file config
 */

const whrJson = require(`${WORKING_DIR}/whr.json`);
const { theme, themePath } = whrJson.config;
// create proxy i.e. http://local.coryweeds:8888
const { host, port, protocol } = whrJson.config;
const proxy = `${protocol || "http"}://${host}${port ? `:${port}` : ""}`;
console.log("WORKING_DIR", WORKING_DIR);
console.log("proxy", proxy);
let outputPath = themePath
  ? `${WORKING_DIR}/${themePath}/${theme}`
  : `${WORKING_DIR}/public/wp-content/themes/${theme}`;
outputPath = path.resolve(outputPath);
// outputPath = path.resolve(path.resolve(__dirname) + outputPath);
module.exports = merge(common, {
  mode: "development",
  devtool: "source-map", // source-map for no eval
  output: {
    clean: false,
    hotUpdateChunkFilename: "hmr/[id].[fullhash].hot-update.js",
    hotUpdateMainFilename: "hmr/[runtime].[fullhash].hot-update.json",
  },
  plugins: [
    new BrowserSyncPlugin(
      {
        proxy,
        files: [
          {
            match: ["**/*.php"],
            fn: function (event, file) {
              if (event === "change") {
                return;
                const bs = require("browser-sync").get("bs-webpack-plugin");
                bs.reload();
              }
            },
          },
        ],
      },
      {
        reload: false,
      },
    ),
  ],
  devServer: {
    // ...defaultConfig.devServer,
    liveReload: false,
    devMiddleware: {
      index: true,
      publicPath: outputPath,
      // publicPath: path.resolve(path.resolve(__dirname) + '/public' + theme),
      serverSideRender: false,
      writeToDisk: (filePath) => {
        // console.log("********** filePath", filePath);
        return true;
      },
    },
    static: {
      // directory: path.resolve(__dirname, '/'),
      staticOptions: {},
      // publicPath: '/',
    },
    open: {
      // target: [proxy],
    },
    // compress: true,
    hot: true,
    host,
    headers: {
      "Access-Control-Allow-Origin": "*",
    },
    // port: 8080,
    port: 3000,
    https: false,
  },
});
