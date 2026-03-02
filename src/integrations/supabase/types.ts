export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[]

export type Database = {
  // Allows to automatically instantiate createClient with right options
  // instead of createClient<Database, { PostgrestVersion: 'XX' }>(URL, KEY)
  __InternalSupabase: {
    PostgrestVersion: "14.1"
  }
  public: {
    Tables: {
      agent_tokens: {
        Row: {
          created_at: string
          created_by: string
          enabled: boolean
          id: string
          name: string
          token: string
        }
        Insert: {
          created_at?: string
          created_by: string
          enabled?: boolean
          id?: string
          name: string
          token: string
        }
        Update: {
          created_at?: string
          created_by?: string
          enabled?: boolean
          id?: string
          name?: string
          token?: string
        }
        Relationships: []
      }
      app_settings: {
        Row: {
          id: string
          key: string
          updated_at: string
          value: string | null
        }
        Insert: {
          id?: string
          key: string
          updated_at?: string
          value?: string | null
        }
        Update: {
          id?: string
          key?: string
          updated_at?: string
          value?: string | null
        }
        Relationships: []
      }
      cable_runs: {
        Row: {
          cable_color: string | null
          cable_length: string | null
          cable_type: string | null
          created_at: string
          dest_id: string
          dest_port: number
          dest_type: string
          floor_plan_id: string | null
          id: string
          label: string | null
          notes: string | null
          source_id: string
          source_port: number
          source_type: string
        }
        Insert: {
          cable_color?: string | null
          cable_length?: string | null
          cable_type?: string | null
          created_at?: string
          dest_id: string
          dest_port: number
          dest_type?: string
          floor_plan_id?: string | null
          id?: string
          label?: string | null
          notes?: string | null
          source_id: string
          source_port: number
          source_type?: string
        }
        Update: {
          cable_color?: string | null
          cable_length?: string | null
          cable_type?: string | null
          created_at?: string
          dest_id?: string
          dest_port?: number
          dest_type?: string
          floor_plan_id?: string | null
          id?: string
          label?: string | null
          notes?: string | null
          source_id?: string
          source_port?: number
          source_type?: string
        }
        Relationships: [
          {
            foreignKeyName: "cable_runs_floor_plan_id_fkey"
            columns: ["floor_plan_id"]
            isOneToOne: false
            referencedRelation: "floor_plans"
            referencedColumns: ["id"]
          },
        ]
      }
      device_edges: {
        Row: {
          connection_type: string | null
          created_at: string
          id: string
          map_id: string
          source_id: string
          target_id: string
        }
        Insert: {
          connection_type?: string | null
          created_at?: string
          id?: string
          map_id: string
          source_id: string
          target_id: string
        }
        Update: {
          connection_type?: string | null
          created_at?: string
          id?: string
          map_id?: string
          source_id?: string
          target_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "device_edges_map_id_fkey"
            columns: ["map_id"]
            isOneToOne: false
            referencedRelation: "maps"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "device_edges_source_id_fkey"
            columns: ["source_id"]
            isOneToOne: false
            referencedRelation: "devices"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "device_edges_target_id_fkey"
            columns: ["target_id"]
            isOneToOne: false
            referencedRelation: "devices"
            referencedColumns: ["id"]
          },
        ]
      }
      device_email_subscriptions: {
        Row: {
          created_at: string
          device_id: string
          email: string
          id: string
          notify_on_critical: boolean | null
          notify_on_offline: boolean | null
          notify_on_online: boolean | null
          notify_on_warning: boolean | null
        }
        Insert: {
          created_at?: string
          device_id: string
          email: string
          id?: string
          notify_on_critical?: boolean | null
          notify_on_offline?: boolean | null
          notify_on_online?: boolean | null
          notify_on_warning?: boolean | null
        }
        Update: {
          created_at?: string
          device_id?: string
          email?: string
          id?: string
          notify_on_critical?: boolean | null
          notify_on_offline?: boolean | null
          notify_on_online?: boolean | null
          notify_on_warning?: boolean | null
        }
        Relationships: [
          {
            foreignKeyName: "device_email_subscriptions_device_id_fkey"
            columns: ["device_id"]
            isOneToOne: false
            referencedRelation: "devices"
            referencedColumns: ["id"]
          },
        ]
      }
      device_ping_results: {
        Row: {
          checked_at: string
          device_id: string
          id: string
          latency_ms: number | null
          packet_loss: number | null
          status: string
        }
        Insert: {
          checked_at?: string
          device_id: string
          id?: string
          latency_ms?: number | null
          packet_loss?: number | null
          status: string
        }
        Update: {
          checked_at?: string
          device_id?: string
          id?: string
          latency_ms?: number | null
          packet_loss?: number | null
          status?: string
        }
        Relationships: [
          {
            foreignKeyName: "device_ping_results_device_id_fkey"
            columns: ["device_id"]
            isOneToOne: false
            referencedRelation: "devices"
            referencedColumns: ["id"]
          },
        ]
      }
      device_status_logs: {
        Row: {
          changed_at: string
          device_id: string
          id: string
          new_status: string
          old_status: string | null
        }
        Insert: {
          changed_at?: string
          device_id: string
          id?: string
          new_status: string
          old_status?: string | null
        }
        Update: {
          changed_at?: string
          device_id?: string
          id?: string
          new_status?: string
          old_status?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "device_status_logs_device_id_fkey"
            columns: ["device_id"]
            isOneToOne: false
            referencedRelation: "devices"
            referencedColumns: ["id"]
          },
        ]
      }
      devices: {
        Row: {
          check_port: number | null
          created_at: string
          critical_latency_threshold: number | null
          critical_packetloss_threshold: number | null
          description: string | null
          icon_size: number | null
          icon_url: string | null
          id: string
          ip_address: string | null
          last_latency: number | null
          last_ping: string | null
          last_ping_result: boolean | null
          map_id: string | null
          monitor_method: string | null
          name: string
          name_text_size: number | null
          ping_interval: number | null
          show_live_ping: boolean | null
          status: string | null
          subchoice: string | null
          type: string | null
          updated_at: string
          user_id: string
          warning_latency_threshold: number | null
          warning_packetloss_threshold: number | null
          x: number | null
          y: number | null
        }
        Insert: {
          check_port?: number | null
          created_at?: string
          critical_latency_threshold?: number | null
          critical_packetloss_threshold?: number | null
          description?: string | null
          icon_size?: number | null
          icon_url?: string | null
          id?: string
          ip_address?: string | null
          last_latency?: number | null
          last_ping?: string | null
          last_ping_result?: boolean | null
          map_id?: string | null
          monitor_method?: string | null
          name: string
          name_text_size?: number | null
          ping_interval?: number | null
          show_live_ping?: boolean | null
          status?: string | null
          subchoice?: string | null
          type?: string | null
          updated_at?: string
          user_id: string
          warning_latency_threshold?: number | null
          warning_packetloss_threshold?: number | null
          x?: number | null
          y?: number | null
        }
        Update: {
          check_port?: number | null
          created_at?: string
          critical_latency_threshold?: number | null
          critical_packetloss_threshold?: number | null
          description?: string | null
          icon_size?: number | null
          icon_url?: string | null
          id?: string
          ip_address?: string | null
          last_latency?: number | null
          last_ping?: string | null
          last_ping_result?: boolean | null
          map_id?: string | null
          monitor_method?: string | null
          name?: string
          name_text_size?: number | null
          ping_interval?: number | null
          show_live_ping?: boolean | null
          status?: string | null
          subchoice?: string | null
          type?: string | null
          updated_at?: string
          user_id?: string
          warning_latency_threshold?: number | null
          warning_packetloss_threshold?: number | null
          x?: number | null
          y?: number | null
        }
        Relationships: [
          {
            foreignKeyName: "devices_map_id_fkey"
            columns: ["map_id"]
            isOneToOne: false
            referencedRelation: "maps"
            referencedColumns: ["id"]
          },
        ]
      }
      docker_containers: {
        Row: {
          container_id: string
          created_at: string
          host_id: string
          id: string
          image: string | null
          internal_ip: string | null
          name: string
          networks: Json | null
          ports: Json | null
          state: string
          status_text: string | null
        }
        Insert: {
          container_id: string
          created_at?: string
          host_id: string
          id?: string
          image?: string | null
          internal_ip?: string | null
          name: string
          networks?: Json | null
          ports?: Json | null
          state?: string
          status_text?: string | null
        }
        Update: {
          container_id?: string
          created_at?: string
          host_id?: string
          id?: string
          image?: string | null
          internal_ip?: string | null
          name?: string
          networks?: Json | null
          ports?: Json | null
          state?: string
          status_text?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "docker_containers_host_id_fkey"
            columns: ["host_id"]
            isOneToOne: false
            referencedRelation: "docker_hosts"
            referencedColumns: ["id"]
          },
        ]
      }
      docker_hosts: {
        Row: {
          active_containers: number
          created_at: string
          docker_version: string | null
          hostname: string
          id: string
          ip: string | null
          last_synced: string | null
          orphaned_volumes: number
          status: string
          updated_at: string
        }
        Insert: {
          active_containers?: number
          created_at?: string
          docker_version?: string | null
          hostname: string
          id?: string
          ip?: string | null
          last_synced?: string | null
          orphaned_volumes?: number
          status?: string
          updated_at?: string
        }
        Update: {
          active_containers?: number
          created_at?: string
          docker_version?: string | null
          hostname?: string
          id?: string
          ip?: string | null
          last_synced?: string | null
          orphaned_volumes?: number
          status?: string
          updated_at?: string
        }
        Relationships: []
      }
      docker_networks: {
        Row: {
          connected_containers: Json | null
          created_at: string
          driver: string | null
          gateway: string | null
          host_id: string
          id: string
          name: string
          scope: string | null
          subnet: string | null
        }
        Insert: {
          connected_containers?: Json | null
          created_at?: string
          driver?: string | null
          gateway?: string | null
          host_id: string
          id?: string
          name: string
          scope?: string | null
          subnet?: string | null
        }
        Update: {
          connected_containers?: Json | null
          created_at?: string
          driver?: string | null
          gateway?: string | null
          host_id?: string
          id?: string
          name?: string
          scope?: string | null
          subnet?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "docker_networks_host_id_fkey"
            columns: ["host_id"]
            isOneToOne: false
            referencedRelation: "docker_hosts"
            referencedColumns: ["id"]
          },
        ]
      }
      floor_plans: {
        Row: {
          created_at: string
          height: number | null
          id: string
          image_url: string | null
          name: string
          updated_at: string
          user_id: string
          width: number | null
        }
        Insert: {
          created_at?: string
          height?: number | null
          id?: string
          image_url?: string | null
          name?: string
          updated_at?: string
          user_id: string
          width?: number | null
        }
        Update: {
          created_at?: string
          height?: number | null
          id?: string
          image_url?: string | null
          name?: string
          updated_at?: string
          user_id?: string
          width?: number | null
        }
        Relationships: []
      }
      host_alert_overrides: {
        Row: {
          cpu_critical: number | null
          cpu_warning: number | null
          created_at: string
          disk_critical: number | null
          disk_warning: number | null
          enabled: boolean | null
          gpu_critical: number | null
          gpu_warning: number | null
          host_ip: string | null
          hostname: string
          id: string
          memory_critical: number | null
          memory_warning: number | null
          status_delay_seconds: number | null
          updated_at: string
        }
        Insert: {
          cpu_critical?: number | null
          cpu_warning?: number | null
          created_at?: string
          disk_critical?: number | null
          disk_warning?: number | null
          enabled?: boolean | null
          gpu_critical?: number | null
          gpu_warning?: number | null
          host_ip?: string | null
          hostname: string
          id?: string
          memory_critical?: number | null
          memory_warning?: number | null
          status_delay_seconds?: number | null
          updated_at?: string
        }
        Update: {
          cpu_critical?: number | null
          cpu_warning?: number | null
          created_at?: string
          disk_critical?: number | null
          disk_warning?: number | null
          enabled?: boolean | null
          gpu_critical?: number | null
          gpu_warning?: number | null
          host_ip?: string | null
          hostname?: string
          id?: string
          memory_critical?: number | null
          memory_warning?: number | null
          status_delay_seconds?: number | null
          updated_at?: string
        }
        Relationships: []
      }
      host_alert_settings: {
        Row: {
          cooldown_minutes: number | null
          cpu_critical_threshold: number | null
          cpu_warning_threshold: number | null
          created_at: string
          disk_critical_threshold: number | null
          disk_warning_threshold: number | null
          enabled: boolean | null
          gpu_critical_threshold: number | null
          gpu_warning_threshold: number | null
          id: string
          memory_critical_threshold: number | null
          memory_warning_threshold: number | null
          updated_at: string
          user_id: string
        }
        Insert: {
          cooldown_minutes?: number | null
          cpu_critical_threshold?: number | null
          cpu_warning_threshold?: number | null
          created_at?: string
          disk_critical_threshold?: number | null
          disk_warning_threshold?: number | null
          enabled?: boolean | null
          gpu_critical_threshold?: number | null
          gpu_warning_threshold?: number | null
          id?: string
          memory_critical_threshold?: number | null
          memory_warning_threshold?: number | null
          updated_at?: string
          user_id: string
        }
        Update: {
          cooldown_minutes?: number | null
          cpu_critical_threshold?: number | null
          cpu_warning_threshold?: number | null
          created_at?: string
          disk_critical_threshold?: number | null
          disk_warning_threshold?: number | null
          enabled?: boolean | null
          gpu_critical_threshold?: number | null
          gpu_warning_threshold?: number | null
          id?: string
          memory_critical_threshold?: number | null
          memory_warning_threshold?: number | null
          updated_at?: string
          user_id?: string
        }
        Relationships: []
      }
      host_metrics: {
        Row: {
          agent_token_id: string | null
          boot_time: string | null
          cpu_usage: number | null
          created_at: string
          disk_total: number | null
          disk_usage: number | null
          first_seen: string
          gpu_usage: number | null
          hostname: string
          id: string
          ip_address: string | null
          last_seen: string
          memory_total: number | null
          memory_usage: number | null
          network_in: number | null
          network_out: number | null
          os_version: string | null
          status: string
          uptime_seconds: number | null
        }
        Insert: {
          agent_token_id?: string | null
          boot_time?: string | null
          cpu_usage?: number | null
          created_at?: string
          disk_total?: number | null
          disk_usage?: number | null
          first_seen?: string
          gpu_usage?: number | null
          hostname: string
          id?: string
          ip_address?: string | null
          last_seen?: string
          memory_total?: number | null
          memory_usage?: number | null
          network_in?: number | null
          network_out?: number | null
          os_version?: string | null
          status?: string
          uptime_seconds?: number | null
        }
        Update: {
          agent_token_id?: string | null
          boot_time?: string | null
          cpu_usage?: number | null
          created_at?: string
          disk_total?: number | null
          disk_usage?: number | null
          first_seen?: string
          gpu_usage?: number | null
          hostname?: string
          id?: string
          ip_address?: string | null
          last_seen?: string
          memory_total?: number | null
          memory_usage?: number | null
          network_in?: number | null
          network_out?: number | null
          os_version?: string | null
          status?: string
          uptime_seconds?: number | null
        }
        Relationships: [
          {
            foreignKeyName: "host_metrics_agent_token_id_fkey"
            columns: ["agent_token_id"]
            isOneToOne: false
            referencedRelation: "agent_tokens"
            referencedColumns: ["id"]
          },
        ]
      }
      host_metrics_history: {
        Row: {
          cpu_usage: number | null
          disk_total: number | null
          disk_usage: number | null
          gpu_usage: number | null
          hostname: string
          id: string
          memory_total: number | null
          memory_usage: number | null
          network_in: number | null
          network_out: number | null
          recorded_at: string
        }
        Insert: {
          cpu_usage?: number | null
          disk_total?: number | null
          disk_usage?: number | null
          gpu_usage?: number | null
          hostname: string
          id?: string
          memory_total?: number | null
          memory_usage?: number | null
          network_in?: number | null
          network_out?: number | null
          recorded_at?: string
        }
        Update: {
          cpu_usage?: number | null
          disk_total?: number | null
          disk_usage?: number | null
          gpu_usage?: number | null
          hostname?: string
          id?: string
          memory_total?: number | null
          memory_usage?: number | null
          network_in?: number | null
          network_out?: number | null
          recorded_at?: string
        }
        Relationships: []
      }
      host_processes: {
        Row: {
          cpu_percent: number | null
          hostname: string
          id: string
          memory_mb: number | null
          pid: number | null
          process_name: string
          process_type: string | null
          recorded_at: string
          status: string | null
        }
        Insert: {
          cpu_percent?: number | null
          hostname: string
          id?: string
          memory_mb?: number | null
          pid?: number | null
          process_name: string
          process_type?: string | null
          recorded_at?: string
          status?: string | null
        }
        Update: {
          cpu_percent?: number | null
          hostname?: string
          id?: string
          memory_mb?: number | null
          pid?: number | null
          process_name?: string
          process_type?: string | null
          recorded_at?: string
          status?: string | null
        }
        Relationships: []
      }
      maps: {
        Row: {
          background_color: string | null
          background_image_url: string | null
          created_at: string
          id: string
          name: string
          offline_delay_seconds: number
          public_view_enabled: boolean | null
          updated_at: string
          user_id: string
        }
        Insert: {
          background_color?: string | null
          background_image_url?: string | null
          created_at?: string
          id?: string
          name?: string
          offline_delay_seconds?: number
          public_view_enabled?: boolean | null
          updated_at?: string
          user_id: string
        }
        Update: {
          background_color?: string | null
          background_image_url?: string | null
          created_at?: string
          id?: string
          name?: string
          offline_delay_seconds?: number
          public_view_enabled?: boolean | null
          updated_at?: string
          user_id?: string
        }
        Relationships: []
      }
      network_graphs: {
        Row: {
          created_at: string
          id: string
          name: string
          updated_at: string
          url: string
          user_id: string
        }
        Insert: {
          created_at?: string
          id?: string
          name: string
          updated_at?: string
          url: string
          user_id: string
        }
        Update: {
          created_at?: string
          id?: string
          name?: string
          updated_at?: string
          url?: string
          user_id?: string
        }
        Relationships: []
      }
      patch_panels: {
        Row: {
          created_at: string
          id: string
          name: string
          panel_type: string | null
          port_count: number | null
          rack_id: string
          rack_position: number | null
        }
        Insert: {
          created_at?: string
          id?: string
          name: string
          panel_type?: string | null
          port_count?: number | null
          rack_id: string
          rack_position?: number | null
        }
        Update: {
          created_at?: string
          id?: string
          name?: string
          panel_type?: string | null
          port_count?: number | null
          rack_id?: string
          rack_position?: number | null
        }
        Relationships: [
          {
            foreignKeyName: "patch_panels_rack_id_fkey"
            columns: ["rack_id"]
            isOneToOne: false
            referencedRelation: "rack_locations"
            referencedColumns: ["id"]
          },
        ]
      }
      ping_results: {
        Row: {
          checked_at: string
          id: string
          response_time_ms: number | null
          status: string
          target_id: string
        }
        Insert: {
          checked_at?: string
          id?: string
          response_time_ms?: number | null
          status: string
          target_id: string
        }
        Update: {
          checked_at?: string
          id?: string
          response_time_ms?: number | null
          status?: string
          target_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "ping_results_target_id_fkey"
            columns: ["target_id"]
            isOneToOne: false
            referencedRelation: "targets"
            referencedColumns: ["id"]
          },
        ]
      }
      profiles: {
        Row: {
          avatar_url: string | null
          created_at: string
          full_name: string | null
          id: string
          updated_at: string
          user_id: string
          username: string | null
        }
        Insert: {
          avatar_url?: string | null
          created_at?: string
          full_name?: string | null
          id?: string
          updated_at?: string
          user_id: string
          username?: string | null
        }
        Update: {
          avatar_url?: string | null
          created_at?: string
          full_name?: string | null
          id?: string
          updated_at?: string
          user_id?: string
          username?: string | null
        }
        Relationships: []
      }
      rack_locations: {
        Row: {
          created_at: string
          floor_plan_id: string
          id: string
          name: string
          rack_units: number | null
          x: number | null
          y: number | null
        }
        Insert: {
          created_at?: string
          floor_plan_id: string
          id?: string
          name: string
          rack_units?: number | null
          x?: number | null
          y?: number | null
        }
        Update: {
          created_at?: string
          floor_plan_id?: string
          id?: string
          name?: string
          rack_units?: number | null
          x?: number | null
          y?: number | null
        }
        Relationships: [
          {
            foreignKeyName: "rack_locations_floor_plan_id_fkey"
            columns: ["floor_plan_id"]
            isOneToOne: false
            referencedRelation: "floor_plans"
            referencedColumns: ["id"]
          },
        ]
      }
      smtp_settings: {
        Row: {
          created_at: string
          enabled: boolean | null
          id: string
          smtp_encryption: string | null
          smtp_from_email: string | null
          smtp_from_name: string | null
          smtp_host: string | null
          smtp_password: string | null
          smtp_port: number | null
          smtp_username: string | null
          updated_at: string
          user_id: string
        }
        Insert: {
          created_at?: string
          enabled?: boolean | null
          id?: string
          smtp_encryption?: string | null
          smtp_from_email?: string | null
          smtp_from_name?: string | null
          smtp_host?: string | null
          smtp_password?: string | null
          smtp_port?: number | null
          smtp_username?: string | null
          updated_at?: string
          user_id: string
        }
        Update: {
          created_at?: string
          enabled?: boolean | null
          id?: string
          smtp_encryption?: string | null
          smtp_from_email?: string | null
          smtp_from_name?: string | null
          smtp_host?: string | null
          smtp_password?: string | null
          smtp_port?: number | null
          smtp_username?: string | null
          updated_at?: string
          user_id?: string
        }
        Relationships: []
      }
      switch_ports: {
        Row: {
          connected_device: string | null
          created_at: string
          device_id: string
          id: string
          notes: string | null
          port_label: string | null
          port_number: number
          speed: string | null
          status: string | null
          vlan: string | null
        }
        Insert: {
          connected_device?: string | null
          created_at?: string
          device_id: string
          id?: string
          notes?: string | null
          port_label?: string | null
          port_number: number
          speed?: string | null
          status?: string | null
          vlan?: string | null
        }
        Update: {
          connected_device?: string | null
          created_at?: string
          device_id?: string
          id?: string
          notes?: string | null
          port_label?: string | null
          port_number?: number
          speed?: string | null
          status?: string | null
          vlan?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "switch_ports_device_id_fkey"
            columns: ["device_id"]
            isOneToOne: false
            referencedRelation: "devices"
            referencedColumns: ["id"]
          },
        ]
      }
      targets: {
        Row: {
          created_at: string
          host: string
          id: string
          name: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          host: string
          id?: string
          name: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          host?: string
          id?: string
          name?: string
          updated_at?: string
        }
        Relationships: []
      }
      user_roles: {
        Row: {
          id: string
          role: Database["public"]["Enums"]["app_role"]
          user_id: string
        }
        Insert: {
          id?: string
          role?: Database["public"]["Enums"]["app_role"]
          user_id: string
        }
        Update: {
          id?: string
          role?: Database["public"]["Enums"]["app_role"]
          user_id?: string
        }
        Relationships: []
      }
    }
    Views: {
      [_ in never]: never
    }
    Functions: {
      has_role: {
        Args: {
          _role: Database["public"]["Enums"]["app_role"]
          _user_id: string
        }
        Returns: boolean
      }
    }
    Enums: {
      app_role: "admin" | "viewer"
    }
    CompositeTypes: {
      [_ in never]: never
    }
  }
}

type DatabaseWithoutInternals = Omit<Database, "__InternalSupabase">

type DefaultSchema = DatabaseWithoutInternals[Extract<keyof Database, "public">]

export type Tables<
  DefaultSchemaTableNameOrOptions extends
    | keyof (DefaultSchema["Tables"] & DefaultSchema["Views"])
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
        DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
      DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])[TableName] extends {
      Row: infer R
    }
    ? R
    : never
  : DefaultSchemaTableNameOrOptions extends keyof (DefaultSchema["Tables"] &
        DefaultSchema["Views"])
    ? (DefaultSchema["Tables"] &
        DefaultSchema["Views"])[DefaultSchemaTableNameOrOptions] extends {
        Row: infer R
      }
      ? R
      : never
    : never

export type TablesInsert<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Insert: infer I
    }
    ? I
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Insert: infer I
      }
      ? I
      : never
    : never

export type TablesUpdate<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Update: infer U
    }
    ? U
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Update: infer U
      }
      ? U
      : never
    : never

export type Enums<
  DefaultSchemaEnumNameOrOptions extends
    | keyof DefaultSchema["Enums"]
    | { schema: keyof DatabaseWithoutInternals },
  EnumName extends DefaultSchemaEnumNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"]
    : never = never,
> = DefaultSchemaEnumNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"][EnumName]
  : DefaultSchemaEnumNameOrOptions extends keyof DefaultSchema["Enums"]
    ? DefaultSchema["Enums"][DefaultSchemaEnumNameOrOptions]
    : never

export type CompositeTypes<
  PublicCompositeTypeNameOrOptions extends
    | keyof DefaultSchema["CompositeTypes"]
    | { schema: keyof DatabaseWithoutInternals },
  CompositeTypeName extends PublicCompositeTypeNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"]
    : never = never,
> = PublicCompositeTypeNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"][CompositeTypeName]
  : PublicCompositeTypeNameOrOptions extends keyof DefaultSchema["CompositeTypes"]
    ? DefaultSchema["CompositeTypes"][PublicCompositeTypeNameOrOptions]
    : never

export const Constants = {
  public: {
    Enums: {
      app_role: ["admin", "viewer"],
    },
  },
} as const
