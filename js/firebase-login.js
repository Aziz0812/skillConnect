// js/firebase-login.js
import { initializeApp } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-app.js";
import { getAuth, GoogleAuthProvider, signInWithPopup, signInWithRedirect, getRedirectResult } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js";

const firebaseConfig = {
  apiKey: "AIzaSyBmgnCsRgeGambbsfBxHy91JV4BsB1wxeM",
  authDomain: "skillconnect-6e03b.firebaseapp.com",
  projectId: "skillconnect-6e03b",
  storageBucket: "skillconnect-6e03b.firebasestorage.app",
  messagingSenderId: "697479842985",
  appId: "1:697479842985:web:b631e05b13a6f91cea2db8"
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const provider = new GoogleAuthProvider();

// helper: POST wrapper with logging
async function postToServer(payload) {
  console.log("[firebase-login] POST to google_login.php payload:", payload);
  try {
    const res = await fetch("google_login.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
      credentials: "same-origin"
    });

    const text = await res.text();
    console.log("[firebase-login] server responded status:", res.status, "text:", text);

    // try parse JSON - if it fails, return the raw text for debugging
    try {
      const json = JSON.parse(text);
      console.log("[firebase-login] parsed JSON:", json);
      return { ok: true, json, status: res.status };
    } catch (e) {
      console.warn("[firebase-login] server returned non-JSON. Full text above.");
      return { ok: false, text, status: res.status };
    }
  } catch (err) {
    console.error("[firebase-login] fetch error:", err);
    return { ok: false, error: err };
  }
}

// Primary sign-in function: try popup, fallback to redirect
window.googleSignIn = async function googleSignIn() {
  console.log("[firebase-login] googleSignIn invoked by user click");
  try {
    // try popup first
    const result = await signInWithPopup(auth, provider);
    console.log("[firebase-login] signInWithPopup result:", result);
    const user = result.user;
    const payload = { email: user.email, name: user.displayName, uid: user.uid, photo: user.photoURL || null };
    const server = await postToServer(payload);
    if (server.ok && server.json?.status === "success") {
      window.location.href = server.json.redirect;
      return;
    }
    alert("Login failed (server). Check console for server response.");
    return;
  } catch (err) {
    console.warn("[firebase-login] signInWithPopup error:", err);
    // If popup blocked, fallback to redirect flow
    if (err?.code === "auth/popup-blocked" || err?.message?.toLowerCase?.().includes("popup")) {
      console.log("[firebase-login] Popup blocked — falling back to signInWithRedirect");
      try {
        await signInWithRedirect(auth, provider);
        // Browser will navigate away — on return, getRedirectResult should be processed below
      } catch (redirErr) {
        console.error("[firebase-login] signInWithRedirect failed:", redirErr);
        alert("Popup blocked and redirect failed. Please allow popups or try a different browser.");
      }
      return;
    }
    // other errors
    console.error("[firebase-login] unexpected error:", err);
    alert("Google sign-in error. See console for details.");
  }
};

// If the page was redirected back by signInWithRedirect, handle the result
(async function handleRedirectResultOnLoad() {
  try {
    const res = await getRedirectResult(auth);
    if (!res) {
      // no redirect result to handle
      return;
    }
    console.log("[firebase-login] getRedirectResult:", res);
    const user = res.user;
    if (user) {
      const payload = { email: user.email, name: user.displayName, uid: user.uid, photo: user.photoURL || null };
      const server = await postToServer(payload);
      if (server.ok && server.json?.status === "success") {
        window.location.href = server.json.redirect;
      } else {
        console.warn("[firebase-login] server response after redirect:", server);
        alert("Login via redirect failed. Check server or console.");
      }
    }
  } catch (e) {
    console.error("[firebase-login] error handling redirect result:", e);
  }
})();
