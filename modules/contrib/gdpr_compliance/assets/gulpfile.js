/**
 * @file
 * Gulp pipe.
 */

'use strict';
const gulp = require('gulp');
const sass = require('gulp-sass');
const touch = require('gulp-touch-cmd');
const plumber = require('gulp-plumber');
const sourcemaps = require('gulp-sourcemaps');

gulp.task('sass', function () {
  return gulp.src('./scss/**', {nodir: true})
    .pipe(plumber())
    .pipe(sourcemaps.init())
    .pipe(sass())
    // .pipe(sourcemaps.write('./_maps')) // Code MAP.
    .pipe(gulp.dest('./css/'))
    .pipe(touch());
});

gulp.task('watch', function () {
  gulp.watch('./scss/**', gulp.series('sass'));
});

gulp.task('default', gulp.parallel('sass', 'watch'));
