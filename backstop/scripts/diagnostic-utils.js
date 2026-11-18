function safeUrl(value) {
  try {
    const url = new URL(value);
    url.username = '';
    url.password = '';
    for (const key of url.searchParams.keys()) {
      if (/token|key|auth|password|signature|secret/i.test(key)) url.searchParams.set(key, '[redacted]');
    }
    return url.href;
  } catch { return '[invalid URL]'; }
}

function safeMessage(message) {
  return message.replace(/https?:\/\/[^\s)]+/g, safeUrl)
    .replace(/eyJ[\w-]+\.[\w-]+\.[\w-]+/g, '[redacted token]');
}

function redact(key, value) {
  if (typeof value !== 'string') return value;
  if (value.startsWith('data:')) return '[inline image]';
  return /^https?:/.test(value) ? safeUrl(value) : safeMessage(value);
}

module.exports = { safeUrl, safeMessage, redact };
