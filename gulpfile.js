// Grab our gulp packages
var gulp = require('gulp'),
    gutil = require('gulp-util'),
    sass = require('gulp-sass'),
    autoprefixer = require('gulp-autoprefixer'),
    cssnano = require('gulp-cssnano'),
    rename = require('gulp-rename'),
    plumber = require('gulp-plumber');

// Compile Sass, Autoprefix and minify
gulp.task('styles', function() {
    return gulp.src('./assets/**/*.scss')
        .pipe(plumber(function(error) {
            gutil.log(gutil.colors.red(error.message));
            this.emit('end');
        }))
        .pipe(sass())
        .pipe(autoprefixer({
            browsers: ['last 2 versions'],
            cascade: false
        }))
        .pipe(gulp.dest('./assets/'))
        .pipe(rename({
            suffix: '.min'
        }))
        .pipe(cssnano())
        .pipe(gulp.dest('./assets/'))
});

// Create a default task 
gulp.task('default', function() {
    gulp.start('styles');
});

// Watch files for changes
gulp.task('watch', function() {

    // Watch .scss files
    gulp.watch('./assets/**/*.scss', ['styles']);

});
