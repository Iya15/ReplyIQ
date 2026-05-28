module.exports = [
  {
    name:  'Loader (widget.js)',
    path:  'public/widget.js',
    limit: '6 KB',
  },
  {
    name:  'App bundle (JS)',
    path:  'dist/app/assets/*.js',
    limit: '100 KB', // laravel-echo + pusher-js add ~18 KB brotli for WS streaming (M3.6)
  },
];
