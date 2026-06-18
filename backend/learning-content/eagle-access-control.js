(function () {
  // 后台正式版由 PHP 负责账号、权限和进度；清掉旧 H5 版可能遗留的离线缓存。
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations()
      .then(registrations => registrations.forEach(registration => registration.unregister()))
      .catch(() => {});
  }
})();
