# Antigravity Agent Rules

## Bangla Language Directive for Implementation & Walkthrough
- **CRITICAL DIRECTIVE**: 
  1. **Implementation Plans & Details (বাস্তবায়ন পরিকল্পনা ও বিস্তারিত)**: Whenever an implementation plan (`implementation_plan.md`), implementation proposal, code modification explanation, or architectural design is created or updated, all content and explanations MUST be written in **Bangla (বাংলা)** language.
  2. **Walkthroughs & Summaries (ওয়াকথ্রু ও সারসংক্ষেপ)**: Whenever a walkthrough document (`walkthrough.md`), verification summary, step-by-step review, or task accomplishment report is created or updated, all details MUST be presented in **Bangla (বাংলা)** language.


## Pre-Update Data Backup Directive (ডকার আপডেট পূর্ববর্তী ব্যাকআপ নির্দেশিকা)
- **CRITICAL DIRECTIVE**: Before making any code deployments, container rebuilds, or server updates to the Docker server (e.g. `192.168.9.9`), the agent MUST first generate and verify a full backup of all network devices (`devices`), topology maps (`maps`), and device connections (`device_edges`). Only after saving the backup may the server update proceed.
