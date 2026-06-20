function renderJson(payload) {
  if (jsonOutput) {
    jsonOutput.textContent = JSON.stringify(payload, null, 2);
  }
}

function showError(error) {
  message.textContent = error.message;
}

function showMessage(text) {
  message.textContent = text;
}

function assertEmail(value) {
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
    throw new Error('Invalid email');
  }
}

function assertPassword(value, required = false) {
  if ((required || value) && value.length < 8) {
    throw new Error('Password min 8 chars');
  }
}
