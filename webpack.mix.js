// webpack.mix.js
let mix = require("laravel-mix");
require('mix-tailwindcss');

mix.setPublicPath('web');

mix.browserSync('localhost:8888');

mix.js('resources/js/app.js', 'js');
mix.sass('resources/scss/app.scss', 'css').tailwind();

if (mix.inProduction()) {
    mix.version();
}