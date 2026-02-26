import { useState } from "react";
import { Navigate } from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";
import { Shield, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";

export default function LoginPage() {
  const { user, loading, signIn, signUp } = useAuth();
  const [isSignUp, setIsSignUp] = useState(false);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [username, setUsername] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [signUpSuccess, setSignUpSuccess] = useState(false);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center login-bg">
        <Shield className="h-10 w-10 text-primary animate-pulse" />
      </div>
    );
  }

  if (user) return <Navigate to="/" replace />;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setSubmitting(true);

    if (isSignUp) {
      const { error } = await signUp(email, password, username);
      if (error) setError(error);
      else setSignUpSuccess(true);
    } else {
      const { error } = await signIn(email, password);
      if (error) setError(error);
    }
    setSubmitting(false);
  };

  return (
    <div className="min-h-screen flex items-center justify-center login-bg p-4">
      <Card className="w-full max-w-md border-border bg-card/90 backdrop-blur shadow-2xl">
        <CardHeader className="text-center space-y-2">
          <div className="mx-auto p-3 rounded-full bg-primary/10 w-fit">
            <Shield className="h-10 w-10 text-primary" />
          </div>
          <CardTitle className="text-2xl font-bold tracking-tight">AMPNM</CardTitle>
          <CardDescription>Advanced Multi-Platform Network Monitor</CardDescription>
        </CardHeader>
        <CardContent>
          {signUpSuccess ? (
            <div className="text-center space-y-3">
              <p className="text-sm text-muted-foreground">
                Account created! Please check your email to verify your account, then sign in.
              </p>
              <Button variant="outline" onClick={() => { setIsSignUp(false); setSignUpSuccess(false); }}>
                Back to Sign In
              </Button>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-4">
              {isSignUp && (
                <div className="space-y-2">
                  <Label htmlFor="username">Username</Label>
                  <Input
                    id="username"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    placeholder="Enter username"
                  />
                </div>
              )}
              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="admin@example.com"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="password">Password</Label>
                <Input
                  id="password"
                  type="password"
                  required
                  minLength={6}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                />
              </div>

              {error && (
                <p className="text-sm text-destructive bg-destructive/10 rounded-md px-3 py-2">{error}</p>
              )}

              <Button type="submit" className="w-full" disabled={submitting}>
                {submitting && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
                {isSignUp ? "Create Account" : "Sign In"}
              </Button>

              <p className="text-center text-sm text-muted-foreground">
                {isSignUp ? "Already have an account?" : "Need an account?"}{" "}
                <button
                  type="button"
                  onClick={() => { setIsSignUp(!isSignUp); setError(""); }}
                  className="text-primary hover:underline font-medium"
                >
                  {isSignUp ? "Sign In" : "Sign Up"}
                </button>
              </p>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
