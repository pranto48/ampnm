Name:           ampnm-agent
Version:        0.1.0
Release:        1%{?dist}
Summary:        AMPNM Linux monitoring agent
License:        Proprietary
BuildArch:      noarch
Requires:       bash, curl, python3, systemd, iproute
Source0:        ampnm-agent.sh
Source1:        ampnm-agent.service
Source2:        config.env.example

%description
AMPNM Linux monitoring agent that sends host metrics to the agent-metrics endpoint and runs as a systemd service.

%prep

%build

%install
mkdir -p %{buildroot}/opt/ampnm-agent
mkdir -p %{buildroot}/etc/systemd/system
mkdir -p %{buildroot}/usr/share/ampnm-agent
install -m 0755 %{SOURCE0} %{buildroot}/opt/ampnm-agent/ampnm-agent.sh
install -m 0644 %{SOURCE1} %{buildroot}/etc/systemd/system/ampnm-agent.service
install -m 0644 %{SOURCE2} %{buildroot}/usr/share/ampnm-agent/config.env.example

%post
systemctl daemon-reload || true
if [ ! -f /etc/ampnm-agent/config.env ] && [ -f /usr/share/ampnm-agent/config.env.example ]; then
  install -d -m 0755 /etc/ampnm-agent
  cp /usr/share/ampnm-agent/config.env.example /etc/ampnm-agent/config.env
  chmod 0600 /etc/ampnm-agent/config.env
fi
systemctl enable --now ampnm-agent.service || true

%preun
if [ $1 -eq 0 ]; then
  systemctl disable --now ampnm-agent.service || true
fi

%files
/opt/ampnm-agent/ampnm-agent.sh
/etc/systemd/system/ampnm-agent.service
/usr/share/ampnm-agent/config.env.example

%changelog
* Mon Mar 23 2026 OpenAI <support@example.com> - 0.1.0-1
- Initial Linux agent package
