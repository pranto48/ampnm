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
      host_metrics: {
        Row: {
          agent_token_id: string | null
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
          status: string
        }
        Insert: {
          agent_token_id?: string | null
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
          status?: string
        }
        Update: {
          agent_token_id?: string | null
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
          status?: string
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
      maps: {
        Row: {
          background_color: string | null
          background_image_url: string | null
          created_at: string
          id: string
          name: string
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
