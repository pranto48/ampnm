import { NextResponse } from "next/server";

export async function POST(request: Request) {
  try {
    const body = await request.json().catch(() => ({}));
    const { recipient, subject, emailHtml, resendApiKey, fromEmail } = body;

    if (!recipient || !subject || !emailHtml) {
      return NextResponse.json(
        { success: false, error: "Missing recipient, subject, or emailHtml parameters" },
        { status: 400 }
      );
    }

    // Determine if we should perform a live dispatch or simulate it
    const apiKey = resendApiKey || process.env.RESEND_API_KEY;
    const sender = fromEmail || process.env.RESEND_FROM_EMAIL || "onboarding@resend.dev";

    if (!apiKey) {
      // Return simulation status
      return NextResponse.json({
        success: true,
        status: "simulated",
        message: "Simulated dispatch succeeded (No Resend API Key configured). Preview is available.",
        data: {
          recipient,
          sender,
          subject,
          bodyPreview: emailHtml,
        }
      });
    }

    // Call Resend API directly
    const resendResponse = await fetch("https://api.resend.com/emails", {
      method: "POST",
      headers: {
        "Authorization": `Bearer ${apiKey}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        from: sender,
        to: recipient,
        subject: subject,
        html: emailHtml,
      }),
    });

    const resendData = await resendResponse.json();

    if (!resendResponse.ok) {
      return NextResponse.json(
        {
          success: false,
          error: resendData.message || "Failed to dispatch email via Resend API",
          status: "failed",
        },
        { status: resendResponse.status }
      );
    }

    return NextResponse.json({
      success: true,
      status: "sent",
      message: "Email successfully delivered via Resend API",
      data: resendData,
    });
  } catch (error: any) {
    console.error("API error in send-email endpoint:", error);
    return NextResponse.json(
      { success: false, error: error.message || "Internal Server Error", status: "failed" },
      { status: 500 }
    );
  }
}
