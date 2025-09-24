import _ from 'lodash';
window._ = _;

import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
window.Pusher = require('pusher-js');

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY || process.env.MIX_PUSHER_APP_KEY || 'local',
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || process.env.MIX_PUSHER_APP_CLUSTER || 'mt1',
    wsHost: window.location.hostname,
    wsPort: 6001,
    wssPort: 6001,
    forceTLS: false,
    enabledTransports: ['ws','wss'],
});