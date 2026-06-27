const crypto = require("crypto");

const base64Str = "QUFBQUFBQUFBQUFBQUFBQTFTZm9SeGdaaUpZUTJibGovK2s3UFRueUh2REwxRUR6MUVLZFFCR054MkFyYnBtS2NvQmZUQzdJZFI2MWNzVWZKZzhCRUw1SHZDa2ZHbW0yL2c1cVZJTWJEV3d4SStWM2FvSEkyUkJhRDFFRFBzUWh2enVWMWtrKzZwTytDWlEwMXJWM2lSU3g2MGYzaW9JckdKNEhKNUhwSTZJTWtZMmluenNlamZhSUEvbz0=";
const data = Buffer.from(base64Str, "base64");

// 1. Extract the 16-byte IV
const iv = data.subarray(0, 16);

// 2. The remaining bytes are the inner base64 string representing the ciphertext
const innerBase64 = data.subarray(16).toString("utf8");

// 3. Decode the inner base64 to get the actual raw encrypted ciphertext
const ciphertext = Buffer.from(innerBase64, "base64");

// 4. Decrypt
const ENCRYPTION_KEY = "ITSupportBD_SecureKey_2024";
const keyBuffer = Buffer.alloc(32);
keyBuffer.write(ENCRYPTION_KEY, "utf-8");

try {
  const decipher = crypto.createDecipheriv("aes-256-cbc", keyBuffer, iv);
  let decrypted = decipher.update(ciphertext);
  decrypted = Buffer.concat([decrypted, decipher.final()]);
  console.log("SUCCESS! Decrypted payload:", decrypted.toString("utf8"));
} catch (e) {
  console.log("Decryption failed:", e.message);
}
