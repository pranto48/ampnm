"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { app } from "@/lib/firebase";
import { getAuth, signInWithEmailAndPassword, signInWithPopup, GoogleAuthProvider } from "firebase/auth";
import { getFirestore, doc, getDoc } from "firebase/firestore";
import { useMonitorStore } from "@/store/use-monitor-store";
import { Activity, ShieldAlert, Key, Mail, ArrowRight, Eye, EyeOff } from "lucide-react";
import { UserProfile } from "@/types";

export default function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const router = useRouter();
  const setProfile = useMonitorStore((s) => s.setProfile);

  const handleEmailSignIn = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const auth = getAuth(app);
      const db = getFirestore(app);
      
      const userCredential = await signInWithEmailAndPassword(auth, email, password);
      // Fetch user profile from firestore with offline fallback
      let userProfile: UserProfile;
      try {
        const userRef = doc(db, "users", userCredential.user.uid);
        const userSnap = await getDoc(userRef);
        if (userSnap.exists()) {
          userProfile = userSnap.data() as UserProfile;
          if (email === "mail@arifmahmud.com") {
            userProfile.role = "admin";
          }
        } else {
          userProfile = {
            uid: userCredential.user.uid,
            name: userCredential.user.displayName || "Administrator",
            email: userCredential.user.email || email,
            role: email === "mail@arifmahmud.com" ? "admin" : "member",
            orgId: "org-default",
            createdAt: new Date().toISOString(),
          };
        }
      } catch (firestoreError) {
        console.warn("Firestore fetch failed, falling back to local client auth:", firestoreError);
        userProfile = {
          uid: userCredential.user.uid,
          name: userCredential.user.displayName || "Administrator",
          email: userCredential.user.email || email,
          role: email === "mail@arifmahmud.com" ? "admin" : "member",
          orgId: "org-default",
          createdAt: new Date().toISOString(),
        };
      }
      setProfile(userProfile);
      router.push("/dashboard");
    } catch (err: any) {
      setError(err.message || "Invalid email or password.");
    } finally {
      setLoading(false);
    }
  };

  const handleGoogleSignIn = async () => {
    setError(null);
    setLoading(true);
    try {
      const auth = getAuth(app);
      const db = getFirestore(app);
      
      const provider = new GoogleAuthProvider();
      const result = await signInWithPopup(auth, provider);
      
      // Fetch or create profile if not present with offline fallback
      let userProfile: UserProfile;
      try {
        const userRef = doc(db, "users", result.user.uid);
        const userSnap = await getDoc(userRef);
        if (userSnap.exists()) {
          userProfile = userSnap.data() as UserProfile;
          if (result.user.email === "mail@arifmahmud.com") {
            userProfile.role = "admin";
          }
        } else {
          userProfile = {
            uid: result.user.uid,
            name: result.user.displayName || "Google Operator",
            email: result.user.email || "",
            role: result.user.email === "mail@arifmahmud.com" ? "admin" : "owner",
            orgId: "org-google-default",
            createdAt: new Date().toISOString(),
          };
        }
      } catch (firestoreError) {
        console.warn("Firestore fetch failed, falling back to local client auth:", firestoreError);
        userProfile = {
          uid: result.user.uid,
          name: result.user.displayName || "Google Operator",
          email: result.user.email || "",
          role: result.user.email === "mail@arifmahmud.com" ? "admin" : "owner",
          orgId: "org-google-default",
          createdAt: new Date().toISOString(),
        };
      }
      setProfile(userProfile);
      router.push("/dashboard");
    } catch (err: any) {
      setError(err.message || "Google authentication failed.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-zinc-950 flex flex-col justify-center py-12 sm:px-6 lg:px-8 px-4 font-sans">
      <div className="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div className="flex justify-center">
          <div className="p-3 bg-blue-600 text-white rounded-2xl shadow-lg shadow-blue-500/20">
            <Activity className="h-8 w-8 animate-pulse" />
          </div>
        </div>
        <h2 className="mt-6 text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-50">
          SaaS Licensing Portal
        </h2>
        <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
          Access portal.itsupport.com.bd dashboard
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white dark:bg-zinc-900 py-8 px-6 shadow-xl rounded-2xl border border-zinc-200 dark:border-zinc-800/80 sm:px-10">
          {error && (
            <div className="mb-4 p-3 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/40 text-red-700 dark:text-red-400 rounded-lg text-sm flex items-start gap-2">
              <ShieldAlert className="h-5 w-5 flex-shrink-0 mt-0.5" />
              <span>{error}</span>
            </div>
          )}

          <form className="space-y-6" onSubmit={handleEmailSignIn}>
            <div>
              <label htmlFor="email" className="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                Email Address
              </label>
              <div className="mt-1.5 relative">
                <input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 bg-zinc-50 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm text-zinc-900 dark:text-zinc-50"
                  placeholder="name@itsupport.com.bd"
                />
                <Mail className="absolute left-3.5 top-3.5 h-4.5 w-4.5 text-zinc-400" />
              </div>
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                Password
              </label>
              <div className="mt-1.5 relative">
                <input
                  id="password"
                  name="password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="current-password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full pl-10 pr-10 py-2.5 bg-zinc-50 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm text-zinc-900 dark:text-zinc-50"
                  placeholder="••••••••"
                />
                <Key className="absolute left-3.5 top-3.5 h-4.5 w-4.5 text-zinc-400" />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-3 text-zinc-400 hover:text-zinc-650 focus:outline-none"
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            <div>
              <button
                type="submit"
                disabled={loading}
                className="w-full flex items-center justify-center gap-2 py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 transition-colors"
              >
                {loading ? "Authenticating..." : "Sign In"}
                <ArrowRight size={16} />
              </button>
            </div>
          </form>

          <div className="mt-6">
            <div className="relative flex py-2 items-center">
              <div className="flex-grow border-t border-zinc-200 dark:border-zinc-800"></div>
              <span className="flex-shrink mx-4 text-zinc-400 text-xs font-semibold uppercase">Or continue with</span>
              <div className="flex-grow border-t border-zinc-200 dark:border-zinc-800"></div>
            </div>

            <div className="mt-4">
              <button
                onClick={handleGoogleSignIn}
                disabled={loading}
                className="w-full flex items-center justify-center gap-2 py-3 px-4 border border-zinc-300 dark:border-zinc-800 rounded-lg shadow-sm text-sm font-semibold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-950 transition-colors cursor-pointer"
              >
                <svg className="h-5 w-5 mr-1" viewBox="0 0 24 24">
                  <path
                    fill="#EA4335"
                    d="M12 5.04c1.66 0 3.2.57 4.38 1.69l3.27-3.27C17.67 1.54 14.98 1 12 1 7.35 1 3.37 3.68 1.42 7.6l3.86 3C6.2 7.54 8.89 5.04 12 5.04z"
                  />
                  <path
                    fill="#4285F4"
                    d="M23.49 12.27c0-.81-.07-1.59-.2-2.35H12v4.51h6.44c-.28 1.47-1.11 2.71-2.36 3.55l3.66 2.84c2.14-1.97 3.39-4.87 3.39-8.55z"
                  />
                  <path
                    fill="#FBBC05"
                    d="M5.28 14.6c-.24-.72-.38-1.5-.38-2.3s.14-1.58.38-2.3L1.42 7.6C.51 9.42 0 11.51 0 13.7s.51 4.28 1.42 6.1l3.86-3.2z"
                  />
                  <path
                    fill="#34A853"
                    d="M12 23c3.24 0 5.97-1.07 7.96-2.91l-3.66-2.84c-1.01.68-2.31 1.09-4.3 1.09-3.11 0-5.8-2.5-6.74-5.56L1.42 19.8C3.37 21.72 7.35 23 12 23z"
                  />
                </svg>
                Google Workspace
              </button>
            </div>
          </div>

          <p className="mt-6 text-center text-xs text-zinc-500">
            Don&apos;t have an organization registered?{" "}
            <Link href="/register" className="font-semibold text-blue-600 hover:underline">
              Create an Account
            </Link>
          </p>

          <div className="mt-6 pt-4 border-t border-zinc-100 dark:border-zinc-800/80 text-center text-[10px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider select-none">
            Developed by IT Support BD
          </div>
        </div>
      </div>
    </div>
  );
}
