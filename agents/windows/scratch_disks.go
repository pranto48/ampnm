package main

import (
	"fmt"
	"github.com/shirou/gopsutil/v3/disk"
)

func main() {
	parts, err := disk.Partitions(true)
	if err != nil {
		fmt.Printf("Error: %v\n", err)
		return
	}
	for _, part := range parts {
		fmt.Printf("Partition: Mountpoint=%s, Device=%s, Fstype=%s, Opts=%v\n", part.Mountpoint, part.Device, part.Fstype, part.Opts)
		usage, err := disk.Usage(part.Mountpoint)
		if err == nil {
			fmt.Printf("  Usage: Total=%d GB, Free=%d GB, UsedPercent=%.2f%%\n", usage.Total/1024/1024/1024, usage.Free/1024/1024/1024, usage.UsedPercent)
		} else {
			fmt.Printf("  Usage error: %v\n", err)
		}
	}
}
