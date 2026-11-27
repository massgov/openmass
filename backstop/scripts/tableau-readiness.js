// Runs before application scripts, including on references without readiness markers.
module.exports = function installTableauReadiness() {
  const mark = (element, status) => {
    const container = element && element.closest('.ma_tableau_container');
    if (container) container.dataset.tableauState = status;
  };
  document.addEventListener('firstinteractive', event => mark(event.target, 'ready'), true);
  document.addEventListener('vizloaderror', event => mark(event.target, 'error'), true);

  // The v2 callback is the public readiness signal. Install synchronously when
  // the SDK defines Viz so even a fast first visualization cannot be missed.
  const instrument = namespace => {
    if (!namespace || namespace.__backstopInstrumented) return namespace;
    Object.defineProperty(namespace, '__backstopInstrumented', { value: true });
    let constructor = namespace.Viz;
    const wrap = Original => typeof Original !== 'function' ? Original : new Proxy(Original, {
      construct(Target, args) {
        const [element, url, options = {}] = args;
        mark(element, 'loading');
        const onFirstInteractive = options.onFirstInteractive;
        return Reflect.construct(Target, [element, url, {
          ...options,
          onFirstInteractive: function (...callbackArgs) {
            mark(element, 'ready');
            return onFirstInteractive && onFirstInteractive.apply(this, callbackArgs);
          }
        }]);
      }
    });
    constructor = wrap(constructor);
    Object.defineProperty(namespace, 'Viz', {
      configurable: true,
      enumerable: true,
      get: () => constructor,
      set: value => { constructor = wrap(value); }
    });
    return namespace;
  };
  let namespace = instrument(window.tableau);
  Object.defineProperty(window, 'tableau', {
    configurable: true,
    enumerable: true,
    get: () => namespace,
    set: value => { namespace = instrument(value); }
  });
};
