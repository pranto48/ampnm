"D:\Anygravety project\ampnm\agents\windows\build\wix\candle.exe" LicenseAgreementDlg_HK.wxs WixUI_HK.wxs product.wxs
"D:\Anygravety project\ampnm\agents\windows\build\wix\light.exe" -ext WixUIExtension -ext WixUtilExtension -sacl -spdb  -out ..\ampnm-agent-setup.msi LicenseAgreementDlg_HK.wixobj WixUI_HK.wixobj product.wixobj

