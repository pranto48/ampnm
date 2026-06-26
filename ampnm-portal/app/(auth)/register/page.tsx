"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { app } from "@/lib/firebase";
import { getAuth, createUserWithEmailAndPassword } from "firebase/auth";
import { getFirestore, doc, collection, writeBatch } from "firebase/firestore";
import { useMonitorStore } from "@/store/use-monitor-store";
import { Activity, ShieldAlert, Key, Mail, User, Building, ArrowRight, Eye, EyeOff } from "lucide-react";
import { UserProfile, Organization } from "@/types";

export default function RegisterPage() {
  const [orgName, setOrgName] = useState("");
  const [fullName, setFullName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const router = useRouter();
  const setProfile = useMonitorStore((s) => s.setProfile);

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const auth = getAuth(app);
      const db = getFirestore(app);

      const userCredential = await createUserWithEmailAndPassword(auth, email, password);
      const uid = userCredential.user.uid;

      // Atomically create organization and user profiles using a Firestore batch
      const batch = writeBatch(db);

      // Generate a unique ID for the new tenant organization
      const orgRef = doc(collection(db, "organizations"));
      const orgId = orgRef.id;

      const orgData: Organization = {
        id: orgId,
        name: orgName,
        createdAt: new Date().toISOString(),
        clientEmail: email,
        licenseCount: 0,
      };

      const userProfile: UserProfile = {
        uid: uid,
        name: fullName,
        email: email,
        role: "owner",
        orgId: orgId,
        createdAt: new Date().toISOString(),
      };

      batch.set(orgRef, orgData);
      batch.set(doc(db, "users", uid), userProfile);

      await batch.commit();

      setProfile(userProfile);
      router.push("/dashboard");
    } catch (err: any) {
      setError(err.message || "Registration failed. Please try again.");
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
          Register Organization
        </h2>
        <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
          Create owner profile and workspace tenant
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

          <form className="space-y-5" onSubmit={handleRegister}>
            <div>
              <label htmlFor="orgName" className="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                Organization Name
              </label>
              <div className="mt-1.5 relative">
                <input
                  id="orgName"
                  name="orgName"
                  type="text"
                  required
                  value={orgName}
                  onChange={(e) => setOrgName(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 bg-zinc-50 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm text-zinc-900 dark:text-zinc-50"
                  placeholder="IT Support Ltd"
                />
                <Building className="absolute left-3.5 top-3.5 h-4.5 w-4.5 text-zinc-400" />
              </div>
            </div>

            <div>
              <label htmlFor="fullName" className="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                Owner Full Name
              </label>
              <div className="mt-1.5 relative">
                <input
                  id="fullName"
                  name="fullName"
                  type="text"
                  required
                  value={fullName}
                  onChange={(e) => setFullName(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 bg-zinc-50 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm text-zinc-900 dark:text-zinc-50"
                  placeholder="Sayed Arif"
                />
                <User className="absolute left-3.5 top-3.5 h-4.5 w-4.5 text-zinc-400" />
              </div>
            </div>

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
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full pl-10 pr-10 py-2.5 bg-zinc-50 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm text-zinc-900 dark:text-zinc-50"
                  placeholder="Min 6 characters"
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

            <div className="pt-2">
              <button
                type="submit"
                disabled={loading}
                className="w-full flex items-center justify-center gap-2 py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 transition-colors"
              >
                {loading ? "Registering..." : "Register Workspace"}
                <ArrowRight size={16} />
              </button>
            </div>
          </form>

          <p className="mt-6 text-center text-xs text-zinc-500">
            Already have an organization?{" "}
            <Link href="/login" className="font-semibold text-blue-600 hover:underline">
              Sign In
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
