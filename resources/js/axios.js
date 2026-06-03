import axios from 'axios';

const token = document.querySelector('meta[name="csrf-token"]')?.content;
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// /api/v1/* RequireJsonHeaders middleware demands BOTH Accept + Content-Type:
// application/json on every request, including GETs (CLAUDE.md frontend rules).
// Axios strips Content-Type on bodiless GETs by default; pin it via an
// interceptor so every axios call carries it regardless of method.
axios.interceptors.request.use((config) => {
    config.headers = config.headers ?? {};
    if (!config.headers['Content-Type'] && !config.headers['content-type']) {
        config.headers['Content-Type'] = 'application/json';
    }
    if (!config.headers['Accept'] && !config.headers['accept']) {
        config.headers['Accept'] = 'application/json';
    }
    return config;
});

export default axios;
