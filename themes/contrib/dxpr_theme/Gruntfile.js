const sass = require("sass");
const autoprefixer = require("autoprefixer");
const postcssPxtorem = require("postcss-pxtorem");
const webpackConfig = require('./webpack.config.js');

module.exports = function (grunt) {
  grunt.initConfig({
    pkg: grunt.file.readJSON("package.json"),
    webpack: {
      myConfig: webpackConfig,
    },
    babel: {
      options: {
        sourceMap: false,
      },
      dist: {
        files: [
          {
            expand: true,
            cwd: 'js/dist/',
            src: ['*.js', '!dxpr-theme-header.js',
                          '!dxpr-theme-multilevel-mobile-nav.js',
                          '!dxpr-theme-full-screen-search.js',
                          '!dxpr-theme-settings-admin.js',
                          '!dxpr-theme-tabs.js',

            ],
            dest: 'js/minified/',
            ext: '.min.js',
          },
        ],
      },
    },
    terser: {
      options: {
        ecma: 2022,
      },
      main: {
        files: [
          {
            expand: true,
            cwd: 'js/minified/',
            src: ['*.min.js', '!dxpr-theme-header.bundle.min.js',
                              '!dxpr-theme-multilevel-mobile-nav.bundle.min.js',
                              '!dxpr-theme-full-screen-search.bundle.min.js',
                              '!dxpr-theme-settings-admin.bundle.min.js',
                              '!dxpr-theme-tabs.bundle.min.js'],
            dest: 'js/minified/',
            ext: '.min.js',
          },
        ],
      },
    },
    sass: {
      options: {
        implementation: sass,
        sourceMap: false,
        outputStyle: "compressed",
      },
      dist: {
        files: [
          {
            expand: true,
            cwd: "scss/",
            src: "**/*.scss",
            dest: "css/",
            ext: ".css",
            extDot: "last",
          },
        ],
      },
    },
    postcss: {
      options: {
        processors: [
          autoprefixer(),
          postcssPxtorem({
            rootValue: 16,
            unitPrecision: 5,
            propList: ["*"],
            selectorBlackList: [],
            replace: true,
            mediaQuery: true,
            minPixelValue: 0,
          }),
        ],
      },
      dist: {
        src: "css/**/*.css",
      },
    },
    watch: {
      css: {
        files: ["scss/**/*.scss"],
        tasks: ["sass", "postcss"],
      },
      js: {
        files: ["js/dist/**/*.js", "!js/minified/**/*.js"],
        tasks: ["webpack", "babel", "terser"],
      },
    },
  });

  grunt.loadNpmTasks("grunt-webpack");
  grunt.loadNpmTasks("grunt-babel");
  grunt.loadNpmTasks("grunt-terser");
  grunt.loadNpmTasks("grunt-sass");
  grunt.loadNpmTasks("grunt-contrib-watch");
  grunt.loadNpmTasks("@lodder/grunt-postcss");

  grunt.registerTask("default", ["watch"]);
};
