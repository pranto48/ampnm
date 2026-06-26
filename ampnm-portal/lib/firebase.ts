import { initializeApp } from "firebase/app";
import { getAnalytics } from "firebase/analytics";

const firebaseConfig = {
  apiKey: "AIzaSyBbg_wbmKs59e_MvETyftbaHsFOfzqGQVI",
  authDomain: "portal-itsupport.firebaseapp.com",
  projectId: "portal-itsupport",
  storageBucket: "portal-itsupport.firebasestorage.app",
  messagingSenderId: "936929281635",
  appId: "1:936929281635:web:ab9dea0bf4415d733b5a56",
  measurementId: "G-Y355P4ST65"
};

const app = initializeApp(firebaseConfig);
// Initialize analytics only on the client side
let analytics;
if (typeof window !== 'undefined') {
  analytics = getAnalytics(app);
}
export { app, analytics };
