const loading = {};

export function loadScript(src) {
    if (document.querySelector('script[src="' + src + '"]')) return Promise.resolve();
    if (loading[src]) return loading[src];

    loading[src] = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => { delete loading[src]; resolve(); };
        script.onerror = () => { delete loading[src]; reject(new Error('Failed to load ' + src)); };
        document.head.appendChild(script);
    });

    return loading[src];
}
