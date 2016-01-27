
var gulp = require('gulp'), 
jshint = require('gulp-jshint'),
concat = require('gulp-concat'),
uglify = require('gulp-uglify'),
minifyCSS = require('gulp-minify-css'),
sass = require('gulp-sass'),
autoprefixer = require('gulp-autoprefixer'),
sourcemaps = require('gulp-sourcemaps'),
gutil = require('gulp-util'),
stripDebug = require('gulp-strip-debug');

var config = {
	bowerDir: './bower_components',
  production: !!gutil.env.production
}

// FA
gulp.task('icons', function() {
    return gulp.src(config.bowerDir + '/font-awesome/fonts/**.*')
        .pipe(gulp.dest('./fonts/'));
});

// JS hint task
gulp.task('jshint', function() {
  gulp.src('./assets/scripts/framework.js')
    .pipe(jshint())
    .pipe(jshint.reporter('default'));
});

// JS concat, strip debugging and minify
gulp.task('scripts', function() {
  gulp.src(
    [
    './assets/scripts/jquery-1.12.0.js', 
    './bower_components/bootstrap-sass/assets/javascripts/bootstrap.js', 
    './assets/scripts/walkway.js', 
    './assets/scripts/jquery.inview.js'
    ])

    .pipe(config.production ? gutil.noop() : sourcemaps.init())

    .pipe(config.production ? stripDebug() : gutil.noop())

  	.pipe(uglify())
    .pipe(concat('framework.js'))

    .pipe(config.production ? gutil.noop() : sourcemaps.write('../maps'))

    .pipe(gulp.dest('./dist/'));
});

// CSS concat, auto-prefix and minify
gulp.task('styles', function() {

  gulp.src(
    [
    './assets/styles/framework.css',
    ])

    // Initialize sourcemaps if this is not a production build
  	.pipe(config.production ? gutil.noop() : sourcemaps.init())

  	.pipe(sass().on('error', sass.logError))
  	.pipe(concat('framework.css'))
  	.pipe(autoprefixer({
	        browsers: ['last 2 versions']
	   }))
  	.pipe(minifyCSS())

    .pipe(config.production ? gutil.noop() : sourcemaps.write('../maps'))

    .pipe(gulp.dest('./dist/'));
});

// default gulp task
gulp.task('default', ['jshint', 'icons', 'scripts', 'styles'], function() {

	/*gulp.watch('./assets/src/scripts/*.js', function() {
		gulp.run('jshint', 'scripts');
	});

	gulp.watch('./assets/styles/*.css', function() {
		gulp.run('styles');
	});*/
});