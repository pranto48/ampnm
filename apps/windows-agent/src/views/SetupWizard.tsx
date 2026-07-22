/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
import { useState } from "react";
import { invoke } from "@tauri-apps/api/core";

interface Props {
  onComplete: () => void;
}

type Step = "welcome" | "server" | "token" | "registering" | "done";

export default function SetupWizard({ onComplete }: Props) {
  const [step, setStep] = useState<Step>("welcome");
  const [serverUrl, setServerUrl] = useState("http://");
  const [token, setToken] = useState("");
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  async function handleRegister() {
    setError("");
    setIsLoading(true);
    setStep("registering");

    try {
      await invoke("save_setup", { serverUrl, enrollmentToken: token });
      await invoke("register");
      setStep("done");
      setTimeout(onComplete, 1500);
    } catch (e: any) {
      setError(String(e));
      setStep("token");
    } finally {
      setIsLoading(false);
    }
  }

  return (
    <div className="wizard">
      {/* Header */}
      <div className="wizard-header">
        <div className="wizard-logo">
          <svg width="36" height="36" viewBox="0 0 48 48" fill="none">
            <rect width="48" height="48" rx="12" fill="#06b6d4" opacity="0.15"/>
            <path d="M12 36L24 12L36 36" stroke="#06b6d4" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"/>
            <path d="M16 28h16" stroke="#06b6d4" strokeWidth="2" strokeLinecap="round"/>
          </svg>
        </div>
        <div>
          <h1 className="wizard-title">AMPNM Agent Setup</h1>
          <p className="wizard-subtitle">Windows Usage Agent</p>
        </div>
      </div>

      {/* Step: Welcome */}
      {step === "welcome" && (
        <div className="wizard-step">
          <div className="step-icon">🖥️</div>
          <h2 className="step-title">Welcome</h2>
          <p className="step-desc">
            This agent monitors your PC's CPU, memory, disk, and network usage and sends
            telemetry to your AMPNM server every few seconds.
          </p>
          <ul className="wizard-features">
            <li>✅ Runs silently in the system tray</li>
            <li>✅ Starts automatically with Windows</li>
            <li>✅ All monitoring is visible to you</li>
            <li>✅ No screenshots or keylogging</li>
            <li>✅ You can pause or stop at any time</li>
          </ul>
          <button className="btn-primary" onClick={() => setStep("server")}>
            Get Started →
          </button>
        </div>
      )}

      {/* Step: Server URL */}
      {step === "server" && (
        <div className="wizard-step">
          <div className="step-icon">🌐</div>
          <h2 className="step-title">Server Address</h2>
          <p className="step-desc">Enter the URL of your AMPNM server.</p>
          <div className="field-group">
            <label className="field-label">AMPNM Server URL</label>
            <input
              id="server-url"
              type="url"
              className="field-input"
              value={serverUrl}
              onChange={(e) => setServerUrl(e.target.value)}
              placeholder="http://192.168.1.10:2266"
              autoFocus
            />
            <p className="field-hint">Examples: http://192.168.1.10:2266 &nbsp;·&nbsp; https://monitor.example.com</p>
          </div>
          <div className="wizard-nav">
            <button className="btn-ghost" onClick={() => setStep("welcome")}>← Back</button>
            <button
              className="btn-primary"
              disabled={!serverUrl.startsWith("http")}
              onClick={() => setStep("token")}
            >
              Next →
            </button>
          </div>
        </div>
      )}

      {/* Step: Enrollment Token */}
      {step === "token" && (
        <div className="wizard-step">
          <div className="step-icon">🔑</div>
          <h2 className="step-title">Enrollment Token</h2>
          <p className="step-desc">
            Paste the enrollment token from the AMPNM Admin panel
            (<strong>Agent Enrollment</strong> page).
          </p>
          <div className="field-group">
            <label className="field-label">Enrollment Token</label>
            <input
              id="enrollment-token"
              type="text"
              className="field-input font-mono"
              value={token}
              onChange={(e) => setToken(e.target.value)}
              placeholder="ampnm_..."
              autoFocus
            />
          </div>
          {error && (
            <div className="error-box">
              <span>⚠️</span> {error}
            </div>
          )}
          <div className="wizard-nav">
            <button className="btn-ghost" onClick={() => setStep("server")}>← Back</button>
            <button
              id="btn-register"
              className="btn-primary"
              disabled={token.length < 8 || isLoading}
              onClick={handleRegister}
            >
              Register →
            </button>
          </div>
        </div>
      )}

      {/* Step: Registering */}
      {step === "registering" && (
        <div className="wizard-step center">
          <div className="spinner"></div>
          <h2 className="step-title">Connecting…</h2>
          <p className="step-desc">Registering this PC with your AMPNM server.</p>
        </div>
      )}

      {/* Step: Done */}
      {step === "done" && (
        <div className="wizard-step center">
          <div className="success-icon">✅</div>
          <h2 className="step-title">Registered!</h2>
          <p className="step-desc">Your PC is now monitored. Opening dashboard…</p>
        </div>
      )}
    </div>
  );
}
