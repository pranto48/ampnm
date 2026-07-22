/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
window.MapApp = window.MapApp || {};

MapApp.state = {
    network: null,
    nodes: new vis.DataSet([]),
    edges: new vis.DataSet([]),
    maps: [],
    currentMapId: null,
    pingIntervals: {},
    animationFrameId: null,
    tick: 0,
    edgeAnimProgress: 0,
    globalRefreshIntervalId: null,
    // Time-based failure tracking per device: { deviceId: timestamp }
    deviceFirstFailTime: {},
    // Agent registration tracking
    knownHostnames: new Set(),
    agentPollIntervalId: null,
    // Per-map mouse-over field visibility settings
    tooltipFieldSettingsByMap: {},
    // Per-map connection tooltip field visibility settings
    connectionTooltipFieldSettingsByMap: {},
    // Per-map mouse-over tooltip display preferences
    tooltipDisplaySettingsByMap: {}
};
